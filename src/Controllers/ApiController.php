<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\RecipeService;
use PrepMeal\Core\Services\MealPlanningService;
use PrepMeal\Core\Services\SeasonalIngredientService;
use PrepMeal\Core\Services\TranslationService;

class ApiController extends BaseController
{
    private RecipeService $recipeService;
    private MealPlanningService $mealPlanningService;
    private SeasonalIngredientService $seasonalService;
    private TranslationService $translationService;

    public function __construct(
        RecipeService $recipeService,
        MealPlanningService $mealPlanningService,
        SeasonalIngredientService $seasonalService,
        TranslationService $translationService
    ) {
        $this->recipeService = $recipeService;
        $this->mealPlanningService = $mealPlanningService;
        $this->seasonalService = $seasonalService;
        $this->translationService = $translationService;
    }

    // === RECETTES ===
    
    public function getRecipes(Request $request, Response $response): Response
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
            'limit' => isset($query['limit']) ? (int) $query['limit'] : 20,
            'offset' => isset($query['offset']) ? (int) $query['offset'] : 0
        ];

        $recipes = $this->recipeService->getRecipes($filters, $locale);
        $total = $this->recipeService->getRecipesCount($filters);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $recipes,
            'pagination' => [
                'total' => $total,
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
                'has_more' => ($filters['offset'] + $filters['limit']) < $total
            ]
        ]);
    }

    public function getRecipe(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getLocale($request);
        $recipeId = $args['id'];
        
        $recipe = $this->recipeService->getRecipe($recipeId, $locale);
        
        if (!$recipe) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Recette non trouvée'
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $recipe
        ]);
    }

    public function searchRecipes(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $query = $request->getQueryParams();
        $searchTerm = $query['q'] ?? '';
        
        if (empty($searchTerm)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Terme de recherche requis'
            ], 400);
        }

        $recipes = $this->recipeService->searchRecipes($searchTerm, $locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $recipes,
            'search_term' => $searchTerm
        ]);
    }

    // === INGRÉDIENTS DE SAISON ===
    
    public function getSeasonalIngredients(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $query = $request->getQueryParams();
        $season = $query['season'] ?? 'current';
        
        $ingredients = $this->seasonalService->getSeasonIngredients($season, $locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $ingredients,
            'season' => $season
        ]);
    }

    public function getCurrentSeasonIngredients(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $ingredients = $this->seasonalService->getCurrentSeasonIngredients($locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $ingredients
        ]);
    }

    // === TYPES DE RÉGIMES ===
    
    public function getDietTypes(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $dietTypes = $this->mealPlanningService->getDietTypes();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $dietTypes
        ]);
    }

    public function getAllergens(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $allergens = $this->mealPlanningService->getAllergens();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $allergens
        ]);
    }

    // === PLANIFICATION ===
    
    public function generatePlan(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();

        // Validation des données
        $preferences = $this->validatePlanPreferences($data);
        
        try {
            $mealPlan = $this->mealPlanningService->generatePlan($preferences);
            
            // Sauvegarder le planning en base
            $this->mealPlanningService->savePlan($mealPlan, $userId);
            
            $planData = $mealPlan->toArray($locale);
            $nutritionalBalance = $this->mealPlanningService->calculateNutritionalBalance($mealPlan);
            $shoppingList = $this->mealPlanningService->generateShoppingList($mealPlan);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'plan' => $planData,
                    'nutritional_balance' => $nutritionalBalance,
                    'shopping_list' => $shoppingList
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la génération du planning: ' . $e->getMessage()
            ], 400);
        }
    }

    public function getMyPlans(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $plans = $this->mealPlanningService->getUserPlans($userId, $locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $plans
        ]);
    }

    public function getPlan(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        
        $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
        
        if (!$plan) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Planning non trouvé'
            ], 404);
        }

        $nutritionalBalance = $this->mealPlanningService->calculateNutritionalBalance($plan);
        $shoppingList = $this->mealPlanningService->generateShoppingList($plan);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'plan' => $plan->toArray($locale),
                'nutritional_balance' => $nutritionalBalance,
                'shopping_list' => $shoppingList
            ]
        ]);
    }

    public function updatePlan(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        $data = $request->getParsedBody();
        
        try {
            $updatedPlan = $this->mealPlanningService->updatePlan($planId, $userId, $data);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $updatedPlan->toArray($this->getLocale($request))
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 400);
        }
    }

    public function deletePlan(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        
        try {
            $this->mealPlanningService->deletePlan($planId, $userId);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Planning supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 400);
        }
    }

    // === FAVORIS ===
    
    public function getFavorites(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $favorites = $this->recipeService->getFavorites($userId, $locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $favorites
        ]);
    }

    public function toggleFavorite(Request $request, Response $response, array $args): Response
    {
        $recipeId = $args['recipeId'];
        $userId = $this->getUserId($request);
        
        $isFavorite = $this->recipeService->toggleFavorite($recipeId, $userId);
        
        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'is_favorite' => $isFavorite,
                'message' => $isFavorite ? 'Recette ajoutée aux favoris' : 'Recette retirée des favoris'
            ]
        ]);
    }

    // === CATÉGORIES ET FILTRES ===
    
    public function getCategories(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $categories = $this->recipeService->getCategories();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $categories
        ]);
    }

    public function getDifficulties(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $difficulties = $this->recipeService->getDifficulties();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $difficulties
        ]);
    }

    public function getSeasons(Request $request, Response $response): Response
    {
        $seasons = $this->seasonalService->getSeasons();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $seasons
        ]);
    }

    // === UTILITAIRES ===
    
    public function getTranslations(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $query = $request->getQueryParams();
        $sections = $query['sections'] ?? ['common'];
        
        if (is_string($sections)) {
            $sections = explode(',', $sections);
        }

        $translations = $this->translationService->getTranslations($locale, $sections);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $translations,
            'locale' => $locale
        ]);
    }

    public function getUnits(Request $request, Response $response): Response
    {
        $units = [
            'metric' => [
                'weight' => ['g', 'kg'],
                'volume' => ['ml', 'l'],
                'length' => ['cm', 'm'],
                'temperature' => '°C'
            ],
            'imperial' => [
                'weight' => ['oz', 'lb'],
                'volume' => ['fl oz', 'cups', 'pints', 'quarts', 'gallons'],
                'length' => ['in', 'ft'],
                'temperature' => '°F'
            ]
        ];

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $units
        ]);
    }

    private function validatePlanPreferences(array $data): array
    {
        $preferences = [
            'period' => $data['period'] ?? 'week',
            'diet_type' => $data['diet_type'] ?? 'equilibre',
            'allergens' => $data['allergens'] ?? [],
            'max_prep_time' => isset($data['max_prep_time']) ? (int) $data['max_prep_time'] : null,
            'servings' => isset($data['servings']) ? (int) $data['servings'] : 2,
            'locale' => $data['locale'] ?? 'fr',
            'selected_ingredients' => $data['selected_ingredients'] ?? [],
            'excluded_ingredients' => $data['excluded_ingredients'] ?? []
        ];

        // Validation des valeurs
        $validPeriods = ['week', 'month', 'year'];
        if (!in_array($preferences['period'], $validPeriods)) {
            throw new \InvalidArgumentException('Période invalide');
        }

        $validDietTypes = ['prise_masse', 'equilibre', 'seche', 'anti_cholesterol', 'vegan', 'vegetarien', 'recettes_simples', 'cetogene', 'paleo', 'sans_gluten', 'mediterraneen'];
        if (!in_array($preferences['diet_type'], $validDietTypes)) {
            throw new \InvalidArgumentException('Type de régime invalide');
        }

        return $preferences;
    }
}
