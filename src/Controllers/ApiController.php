<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\RecipeService;
use PrepMeal\Core\Services\MealPlanningService;
use PrepMeal\Core\Services\SeasonalIngredientService;
use PrepMeal\Core\Services\TranslationService;
use PrepMeal\Core\Services\UnitConversionService;

class ApiController extends BaseController
{
    private RecipeService $recipeService;
    private MealPlanningService $mealPlanningService;
    private SeasonalIngredientService $seasonalService;
    private TranslationService $translationService;
    private UnitConversionService $unitConversionService;

    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        RecipeService $recipeService,
        MealPlanningService $mealPlanningService,
        SeasonalIngredientService $seasonalService,
        TranslationService $translationService,
        UnitConversionService $unitConversionService
    ) {
        parent::__construct($view, $translationService);
        $this->recipeService = $recipeService;
        $this->mealPlanningService = $mealPlanningService;
        $this->seasonalService = $seasonalService;
        $this->translationService = $translationService;
        $this->unitConversionService = $unitConversionService;
    }

    // === RECETTES ===
    
    public function getRecipes(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
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
        $locale = $this->getDefaultLocale();
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
        $locale = $this->getDefaultLocale();
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

    // === PLANIFICATION DE REPAS ===

    public function generatePlan(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();

        try {
            // Validation des données
            $preferences = $this->validatePlanPreferences($data);
            
            // Générer le planning
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
                    'nutritionalBalance' => $nutritionalBalance,
                    'shoppingList' => $shoppingList
                ],
                'message' => 'Planning généré avec succès'
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
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        
        $plans = $this->mealPlanningService->getUserPlans($userId, $locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $plans
        ]);
    }

    public function getPlan(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getDefaultLocale();
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
                'nutritionalBalance' => $nutritionalBalance,
                'shoppingList' => $shoppingList
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
                'data' => $updatedPlan->toArray($this->getDefaultLocale()),
                'message' => 'Planning mis à jour avec succès'
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
            $success = $this->mealPlanningService->deletePlan($planId, $userId);
            
            if ($success) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Planning supprimé avec succès'
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Erreur lors de la suppression'
                ], 400);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 400);
        }
    }

    public function getShoppingList(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        
        $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
        
        if (!$plan) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Planning non trouvé'
            ], 404);
        }

        $shoppingList = $this->mealPlanningService->generateShoppingList($plan);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $shoppingList
        ]);
    }

    public function getNutritionalBalance(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getDefaultLocale();
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

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $nutritionalBalance
        ]);
    }

    public function savePreferences(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();
        
        try {
            $preferences = [
                'diet_type' => $data['diet_type'] ?? 'equilibre',
                'excluded_allergens' => $data['excluded_allergens'] ?? [],
                'selected_ingredients' => $data['selected_ingredients'] ?? [],
                'period' => $data['period'] ?? 'week',
                'servings' => isset($data['servings']) ? (int) $data['servings'] : 2,
                'units' => $data['units'] ?? 'metric'
            ];

            $success = $this->mealPlanningService->saveUserPreferences($userId, $preferences);
            
            if ($success) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Préférences sauvegardées avec succès'
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Erreur lors de la sauvegarde'
                ], 400);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ], 400);
        }
    }

    public function getPeriods(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $periods = $this->mealPlanningService->getPeriods();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $periods
        ]);
    }

    // === INGRÉDIENTS DE SAISON ===

    public function getSeasonalIngredients(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $query = $request->getQueryParams();
        $season = $query['season'] ?? null;
        
        if ($season) {
            $ingredients = $this->seasonalService->getSeasonalIngredients($season);
        } else {
            $ingredients = $this->seasonalService->getCurrentSeasonalIngredients();
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $ingredients
        ]);
    }

    public function getCurrentSeasonIngredients(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $ingredients = $this->seasonalService->getCurrentSeasonalIngredients();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $ingredients
        ]);
    }

    // === TYPES DE RÉGIME ===

    public function getDietTypes(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $dietTypes = $this->mealPlanningService->getDietTypes();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $dietTypes
        ]);
    }

    // === ALLERGÈNES ===

    public function getAllergens(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $allergens = $this->mealPlanningService->getAllergens();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $allergens
        ]);
    }

    // === FAVORIS ===

    public function getFavorites(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        
        $favorites = $this->recipeService->getUserFavorites($userId, $locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $favorites
        ]);
    }

    public function toggleFavorite(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        $recipeId = $args['id'];
        
        try {
            $isFavorite = $this->recipeService->toggleFavorite($userId, $recipeId);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'is_favorite' => $isFavorite
                ],
                'message' => $isFavorite ? 'Recette ajoutée aux favoris' : 'Recette retirée des favoris'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la modification des favoris: ' . $e->getMessage()
            ], 400);
        }
    }

    // === CATÉGORIES ===

    public function getCategories(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $categories = $this->translationService->getCategories($locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $categories
        ]);
    }

    // === DIFFICULTÉS ===

    public function getDifficulties(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $difficulties = $this->translationService->getDifficulties($locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $difficulties
        ]);
    }

    // === SAISONS ===

    public function getSeasons(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $seasons = $this->translationService->getSeasons($locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $seasons
        ]);
    }

    // === TRADUCTIONS ===

    public function getTranslations(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $query = $request->getQueryParams();
        $sections = $query['sections'] ?? [];
        
        if (is_string($sections)) {
            $sections = explode(',', $sections);
        }

        $translations = $this->translationService->getTranslations($locale, $sections);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $translations
        ]);
    }

    // === UNITÉS ===

    public function getUnits(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $units = $this->translationService->getUnits($locale);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $units
        ]);
    }

    public function convertUnits(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        try {
            $quantity = $data['quantity'] ?? null;
            $fromUnit = $data['from_unit'] ?? null;
            $toUnit = $data['to_unit'] ?? null;
            $type = $data['type'] ?? 'weight';

            if (!$quantity || !$fromUnit || !$toUnit) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Quantité, unité source et unité cible requises'
                ], 400);
            }

            $convertedQuantity = $this->unitConversionService->convertQuantity($quantity, $fromUnit, $toUnit, $type);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'original' => $quantity,
                    'converted' => $convertedQuantity,
                    'from_unit' => $fromUnit,
                    'to_unit' => $toUnit
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la conversion: ' . $e->getMessage()
            ], 400);
        }
    }

    // === MÉTHODES PRIVÉES ===

    private function validatePlanPreferences(array $data): array
    {
        $preferences = [
            'period' => $data['period'] ?? 'week',
            'diet_type' => $data['diet_type'] ?? 'equilibre',
            'excluded_allergens' => $data['excluded_allergens'] ?? [],
            'selected_ingredients' => $data['selected_ingredients'] ?? [],
            'max_prep_time' => isset($data['max_prep_time']) ? (int) $data['max_prep_time'] : null,
            'servings' => isset($data['servings']) ? (int) $data['servings'] : 2,
            'locale' => $data['locale'] ?? 'fr',
            'units' => $data['units'] ?? 'metric'
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
