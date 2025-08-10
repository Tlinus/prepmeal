<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\RecipeService;
use PrepMeal\Core\Services\TranslationService;
use PrepMeal\Core\Database\RecipeRepository;

class RecipeController extends BaseController
{
    private RecipeService $recipeService;
    private TranslationService $translationService;
    private RecipeRepository $recipeRepository;

    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        RecipeService $recipeService,
        RecipeRepository $recipeRepository
    ) {
        parent::__construct($view, $translationService);
        $this->recipeService = $recipeService;
        $this->translationService = $translationService;
        $this->recipeRepository = $recipeRepository;
    }

    public function index(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $query = $request->getQueryParams();
        
        $filters = [
            'category' => $query['category'] ?? null,
            'difficulty' => $query['difficulty'] ?? null,
            'diet_type' => $query['diet_type'] ?? null,
            'allergens' => $query['allergens'] ?? [],
            'search' => $query['search'] ?? null,
            'season' => $query['season'] ?? null,
            'max_prep_time' => $query['max_prep_time'] ?? null
        ];

        // Get all recipes and apply filters
        $allRecipes = $this->recipeService->getAllRecipes($filters);
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $allRecipes = $this->recipeService->searchRecipes($filters['search'], $filters);
        }

        // Apply sorting
        $sortBy = $query['sort'] ?? 'name_asc';
        $allRecipes = $this->sortRecipes($allRecipes, $sortBy, $locale);

        // Get filter options
        $categories = $this->recipeService->getCategories();
        $difficulties = $this->recipeService->getDifficulties();
        $dietTypes = $this->recipeService->getDietTypes();
        $allergens = $this->recipeService->getAllergens();
        
        // Get user favorites if logged in
        $userId = $this->getUserId($request);
        $userFavorites = [];
        if ($userId) {
            $userFavorites = $this->recipeService->getUserFavorites($userId);
        }

        // Mark recipes as favorites
        $recipes = array_map(function($recipe) use ($userFavorites) {
            $recipe->setIsFavorite(in_array($recipe->getId(), array_map(fn($f) => $f->getId(), $userFavorites)));
            return $recipe;
        }, $allRecipes);

        // Prepare categories and difficulties for the template
        $categoryOptions = $this->prepareFilterOptions($categories, 'categories', $locale);
        $difficultyOptions = $this->prepareFilterOptions($difficulties, 'difficulties', $locale);
        $dietTypeOptions = $this->prepareFilterOptions($dietTypes, 'diet_types', $locale);
        $allergenOptions = $this->prepareFilterOptions($allergens, 'allergens', $locale);

        $data = [
            'recipes' => array_map(fn($r) => $r->toArray($locale), $recipes),
            'categories' => $categoryOptions,
            'difficulties' => $difficultyOptions,
            'dietTypes' => $dietTypeOptions,
            'allergens' => $allergenOptions,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'locale' => $locale,
            'stats' => [
                'total' => count($allRecipes),
                'filtered' => count($recipes),
                'favorites' => count($userFavorites)
            ],
            'translations' => $this->translationService->getTranslations($locale),
            'locale' => $locale
        ];

        return $this->render($response, 'recipes/index.twig', $data);
    }

    private function sortRecipes(array $recipes, string $sortBy, string $locale): array
    {
        switch ($sortBy) {
            case 'name_desc':
                usort($recipes, function($a, $b) use ($locale) {
                    return strcmp($b->getTitle($locale), $a->getTitle($locale));
                });
                break;
            case 'difficulty_asc':
                usort($recipes, function($a, $b) {
                    $difficultyOrder = ['facile' => 1, 'moyen' => 2, 'difficile' => 3];
                    return $difficultyOrder[$a->getDifficulty()] <=> $difficultyOrder[$b->getDifficulty()];
                });
                break;
            case 'difficulty_desc':
                usort($recipes, function($a, $b) {
                    $difficultyOrder = ['facile' => 1, 'moyen' => 2, 'difficile' => 3];
                    return $difficultyOrder[$b->getDifficulty()] <=> $difficultyOrder[$a->getDifficulty()];
                });
                break;
            case 'time_asc':
                usort($recipes, function($a, $b) {
                    return $a->getTotalTime() <=> $b->getTotalTime();
                });
                break;
            case 'time_desc':
                usort($recipes, function($a, $b) {
                    return $b->getTotalTime() <=> $a->getTotalTime();
                });
                break;
            default: // name_asc
                usort($recipes, function($a, $b) use ($locale) {
                    return strcmp($a->getTitle($locale), $b->getTitle($locale));
                });
                break;
        }
        
        return $recipes;
    }

    private function prepareFilterOptions(array $data, string $type, string $locale): array
    {
        $options = [];
        
        switch ($type) {
            case 'categories':
                foreach ($data as $key => $count) {
                    $options[] = [
                        'value' => $key,
                        'label' => $this->translationService->translate("recipes.categories.{$key}", $locale),
                        'count' => $count
                    ];
                }
                break;
            case 'difficulties':
                foreach ($data as $key => $count) {
                    $options[] = [
                        'value' => $key,
                        'label' => $this->translationService->translate("recipes.difficulties.{$key}", $locale),
                        'count' => $count
                    ];
                }
                break;
            case 'diet_types':
                foreach ($data as $key => $count) {
                    $options[] = [
                        'value' => $key,
                        'label' => $this->translationService->translate("diet_types.{$key}", $locale),
                        'count' => $count
                    ];
                }
                break;
            case 'allergens':
                foreach ($data as $key => $count) {
                    $options[] = [
                        'value' => $key,
                        'label' => $this->translationService->translate("allergens.{$key}", $locale),
                        'count' => $count
                    ];
                }
                break;
        }
        
        return $options;
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getLocale($request);
        $recipeId = $args['id'];
        
        $recipe = $this->recipeService->getRecipe($recipeId, $locale);
        
        if (!$recipe) {
            return $this->redirect($response, '/recipes');
        }

        $relatedRecipes = $this->recipeService->getRelatedRecipes($recipe, $locale);
        $isFavorite = $this->recipeService->isFavorite($recipeId, $this->getUserId($request));

        $data = [
            'recipe' => $recipe->toArray($locale),
            'relatedRecipes' => array_map(fn($r) => $r->toArray($locale), $relatedRecipes),
            'isFavorite' => $isFavorite,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['recipes', 'common'])
        ];

        return $this->render($response, 'recipes/show.twig', $data);
    }

    public function favorites(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        if (!$userId) {
            return $this->redirect($response, '/login');
        }
        
        $favorites = $this->recipeService->getUserFavorites($userId);
        
        $data = [
            'recipes' => array_map(fn($r) => $r->toArray($locale), $favorites),
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['recipes', 'common'])
        ];
        
        return $this->render($response, 'recipes/favorites.twig', $data);
    }

    public function addToFavorites(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        
        if (!$userId) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'User not authenticated'], 401);
        }
        
        $recipeId = $args['id'];
        $success = $this->recipeService->toggleFavorite($userId, $recipeId);
        
        return $this->jsonResponse($response, [
            'success' => $success,
            'message' => $success ? 'Recipe added to favorites' : 'Recipe removed from favorites'
        ]);
    }

    public function toggleFavorite(Request $request, Response $response, array $args): Response
    {
        $recipeId = $args['id'];
        $userId = $this->getUserId($request);
        
        $isFavorite = $this->recipeService->toggleFavorite($recipeId, $userId);
        
        return $this->jsonResponse($response, [
            'success' => true,
            'isFavorite' => $isFavorite,
            'message' => $isFavorite ? 'Recette ajoutée aux favoris' : 'Recette retirée des favoris'
        ]);
    }

    public function search(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $query = $request->getQueryParams();
        $searchTerm = $query['q'] ?? '';
        
        if (empty($searchTerm)) {
            return $this->redirect($response, '/recipes');
        }

        $recipes = $this->recipeService->searchRecipes($searchTerm, $locale);

        $data = [
            'recipes' => array_map(fn($r) => $r->toArray($locale), $recipes),
            'searchTerm' => $searchTerm,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['recipes', 'common'])
        ];

        return $this->render($response, 'recipes/search.twig', $data);
    }

    public function byCategory(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getLocale($request);
        $category = $args['category'];
        
        $recipes = $this->recipeService->getRecipesByCategory($category, $locale);
        $categoryInfo = $this->recipeService->getCategoryInfo($category, $locale);

        $data = [
            'recipes' => array_map(fn($r) => $r->toArray($locale), $recipes),
            'category' => $categoryInfo,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['recipes', 'common'])
        ];

        return $this->render($response, 'recipes/category.twig', $data);
    }

    public function seasonal(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $season = $request->getQueryParams()['season'] ?? 'current';
        
        $recipes = $this->recipeService->getSeasonalRecipes($season, $locale);
        $seasonInfo = $this->recipeService->getSeasonInfo($season, $locale);

        $data = [
            'recipes' => array_map(fn($r) => $r->toArray($locale), $recipes),
            'season' => $seasonInfo,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['recipes', 'common'])
        ];

        return $this->render($response, 'recipes/seasonal.twig', $data);
    }
}
