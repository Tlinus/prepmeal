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
        RecipeService $recipeService,
        TranslationService $translationService,
        RecipeRepository $recipeRepository
    ) {
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
            'season' => $query['season'] ?? null
        ];

        $recipes = $this->recipeService->getRecipes($filters, $locale);
        $categories = $this->recipeService->getCategories();
        $difficulties = $this->recipeService->getDifficulties();
        $dietTypes = $this->recipeService->getDietTypes();
        $allergens = $this->recipeService->getAllergens();

        $data = [
            'recipes' => $recipes,
            'categories' => $categories,
            'difficulties' => $difficulties,
            'dietTypes' => $dietTypes,
            'allergens' => $allergens,
            'filters' => $filters,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['recipes', 'common'])
        ];

        return $this->render($response, 'recipes/index.twig', $data);
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
            'recipe' => $recipe,
            'relatedRecipes' => $relatedRecipes,
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
        
        $favorites = $this->recipeService->getFavorites($userId, $locale);

        $data = [
            'recipes' => $favorites,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['recipes', 'common'])
        ];

        return $this->render($response, 'recipes/favorites.twig', $data);
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
            'recipes' => $recipes,
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
            'recipes' => $recipes,
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
            'recipes' => $recipes,
            'season' => $seasonInfo,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['recipes', 'common'])
        ];

        return $this->render($response, 'recipes/seasonal.twig', $data);
    }
}
