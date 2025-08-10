<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

use PrepMeal\Core\Database\RecipeRepository;
use PrepMeal\Core\Models\Recipe;
use PrepMeal\Core\Models\MealPlan;
use PrepMeal\Core\Models\MealPlanDay;

class MealPlanningService
{
    private RecipeRepository $recipeRepository;
    private SeasonalIngredientService $seasonalService;
    private UnitConversionService $unitService;

    public function __construct(
        RecipeRepository $recipeRepository,
        SeasonalIngredientService $seasonalService,
        UnitConversionService $unitService
    ) {
        $this->recipeRepository = $recipeRepository;
        $this->seasonalService = $seasonalService;
        $this->unitService = $unitService;
    }

    public function generatePlan(array $preferences): MealPlan
    {
        $period = $preferences['period'] ?? 'week';
        $dietType = $preferences['diet_type'] ?? 'equilibre';
        $allergens = $preferences['excluded_allergens'] ?? [];
        $selectedIngredients = $preferences['selected_ingredients'] ?? [];
        $maxPrepTime = $preferences['max_prep_time'] ?? null;
        $servings = $preferences['servings'] ?? 2;
        $locale = $preferences['locale'] ?? 'fr';
        $units = $preferences['units'] ?? 'metric';

        // Calculer la période
        $startDate = new \DateTime();
        $endDate = $this->calculateEndDate($startDate, $period);

        // Récupérer les recettes disponibles avec filtrage
        $availableRecipes = $this->getAvailableRecipes($dietType, $allergens, $selectedIngredients, $maxPrepTime);

        // Générer le planning
        $planDays = $this->generatePlanDays($startDate, $endDate, $availableRecipes, $servings, $locale, $selectedIngredients, $units);

        return new MealPlan(
            uniqid('plan_'),
            $startDate,
            $endDate,
            $planDays,
            $preferences
        );
    }

    private function calculateEndDate(\DateTime $startDate, string $period): \DateTime
    {
        $endDate = clone $startDate;

        switch ($period) {
            case 'week':
                $endDate->add(new \DateInterval('P7D'));
                break;
            case 'month':
                $endDate->add(new \DateInterval('P1M'));
                break;
            case 'year':
                $endDate->add(new \DateInterval('P1Y'));
                break;
            default:
                $endDate->add(new \DateInterval('P7D'));
        }

        return $endDate;
    }

    private function getAvailableRecipes(string $dietType, array $allergens, array $selectedIngredients, ?int $maxPrepTime): array
    {
        try {
            $filters = [
                // Ne pas filtrer par diet_type au niveau de la base de données
                // car cela peut être trop restrictif et causer des résultats vides
                'allergens' => $allergens
            ];

            if ($maxPrepTime) {
                $filters['max_prep_time'] = $maxPrepTime;
            }

            // Récupérer toutes les recettes sans filtrage strict par diet_type
            $recipes = $this->recipeRepository->findAll($filters);

            // Appliquer les filtres de régime alimentaire en PHP
            $recipes = $this->filterRecipesByDietType($recipes, $dietType);

            // Filtrer par ingrédients sélectionnés si spécifiés
            if (!empty($selectedIngredients)) {
                $recipes = $this->filterRecipesByIngredients($recipes, $selectedIngredients);
            }

            return $recipes;
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des recettes: ' . $e->getMessage());
            return [];
        }
    }

    private function filterRecipesByDietType(array $recipes, string $dietType): array
    {
        $filteredRecipes = [];

        foreach ($recipes as $recipe) {
            $include = false;

            switch ($dietType) {
                case 'prise_masse':
                    // Recettes riches en protéines et calories
                    $nutrition = $recipe->getNutrition();
                    if (isset($nutrition['protein']) && $nutrition['protein'] >= 20 && 
                        isset($nutrition['calories']) && $nutrition['calories'] >= 400) {
                        $include = true;
                    }
                    break;

                case 'equilibre':
                    // Recettes équilibrées - inclure toutes les recettes car 'equilibre' est une catégorie
                    // et non une restriction alimentaire
                    $include = true;
                    break;

                case 'seche':
                    // Recettes pauvres en calories mais riches en protéines
                    $nutrition = $recipe->getNutrition();
                    if (isset($nutrition['calories']) && $nutrition['calories'] <= 300 && 
                        isset($nutrition['protein']) && $nutrition['protein'] >= 15) {
                        $include = true;
                    }
                    break;

                case 'anti_cholesterol':
                    // Recettes pauvres en graisses saturées
                    $nutrition = $recipe->getNutrition();
                    if (isset($nutrition['fat']) && $nutrition['fat'] <= 15) {
                        $include = true;
                    }
                    break;

                case 'vegan':
                    if ($recipe->isVegan()) {
                        $include = true;
                    }
                    break;

                case 'vegetarien':
                    if ($recipe->isVegetarian()) {
                        $include = true;
                    }
                    break;

                case 'recettes_simples':
                    // Recettes avec maximum 5 ingrédients et 30min de préparation
                    $ingredients = $recipe->getIngredients();
                    $totalTime = $recipe->getPrepTime() + $recipe->getCookTime();
                    if (count($ingredients) <= 5 && $totalTime <= 30) {
                        $include = true;
                    }
                    break;

                case 'cetogene':
                    if ($this->isKetoFriendly($recipe)) {
                        $include = true;
                    }
                    break;

                case 'paleo':
                    if ($this->isPaleoFriendly($recipe)) {
                        $include = true;
                    }
                    break;

                case 'sans_gluten':
                    if ($recipe->isGlutenFree()) {
                        $include = true;
                    }
                    break;

                case 'mediterraneen':
                    if ($this->isMediterraneanFriendly($recipe)) {
                        $include = true;
                    }
                    break;

                default:
                    // Pour tout autre type de régime non reconnu, inclure toutes les recettes
                    $include = true;
            }

            if ($include) {
                $filteredRecipes[] = $recipe;
            }
        }

        return $filteredRecipes;
    }

    private function isKetoFriendly(Recipe $recipe): bool
    {
        $nutrition = $recipe->getNutrition();
        if (!isset($nutrition['carbs']) || !isset($nutrition['protein']) || !isset($nutrition['fat'])) {
            return false;
        }

        // Ratio cétogène: 70% lipides, 25% protéines, 5% glucides
        $totalCalories = $nutrition['calories'] ?? 0;
        if ($totalCalories === 0) return false;

        $carbPercentage = ($nutrition['carbs'] * 4) / $totalCalories * 100;
        return $carbPercentage <= 10; // Maximum 10% de glucides
    }

    private function isPaleoFriendly(Recipe $recipe): bool
    {
        $ingredients = $recipe->getIngredients();
        $excludedIngredients = ['blé', 'wheat', 'lait', 'milk', 'sucre', 'sugar', 'légumineuses', 'legumes'];

        foreach ($ingredients as $ingredient) {
            $ingredientName = strtolower($ingredient->getName('fr'));
            foreach ($excludedIngredients as $excluded) {
                if (strpos($ingredientName, $excluded) !== false) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isMediterraneanFriendly(Recipe $recipe): bool
    {
        $ingredients = $recipe->getIngredients();
        $mediterraneanIngredients = ['huile d\'olive', 'olive oil', 'tomate', 'tomato', 'poisson', 'fish', 'légumes', 'vegetables'];

        $mediterraneanCount = 0;
        foreach ($ingredients as $ingredient) {
            $ingredientName = strtolower($ingredient->getName('fr'));
            foreach ($mediterraneanIngredients as $mediterranean) {
                if (strpos($ingredientName, $mediterranean) !== false) {
                    $mediterraneanCount++;
                    break;
                }
            }
        }

        return $mediterraneanCount >= 2; // Au moins 2 ingrédients méditerranéens
    }

    private function filterRecipesByIngredients(array $recipes, array $selectedIngredients): array
    {
        if (empty($selectedIngredients)) {
            return $recipes;
        }

        $filteredRecipes = [];
        foreach ($recipes as $recipe) {
            $recipeIngredients = $recipe->getIngredients();
            $hasSelectedIngredient = false;

            foreach ($recipeIngredients as $ingredient) {
                $ingredientName = strtolower($ingredient->getName('fr'));
                
                foreach ($selectedIngredients as $selectedIngredient) {
                    if (strpos($ingredientName, strtolower($selectedIngredient)) !== false) {
                        $hasSelectedIngredient = true;
                        break 2;
                    }
                }
            }

            if ($hasSelectedIngredient) {
                $filteredRecipes[] = $recipe;
            }
        }

        return $filteredRecipes;
    }

    private function generatePlanDays(\DateTime $startDate, \DateTime $endDate, array $recipes, int $servings, string $locale, array $selectedIngredients = [], string $units = 'metric'): array
    {
        $planDays = [];
        $currentDate = clone $startDate;
        $recipePool = $recipes;
        $usedRecipes = [];

        while ($currentDate <= $endDate) {
            $dayRecipes = $this->selectRecipesForDay($currentDate, $recipePool, $servings, $locale, $selectedIngredients, $units);
            
            // Marquer les recettes utilisées pour éviter la répétition
            foreach ($dayRecipes as $meal => $recipe) {
                $usedRecipes[] = $recipe->getId();
            }
            
            $planDays[] = new MealPlanDay(
                $currentDate,
                $dayRecipes
            );

            $currentDate->add(new \DateInterval('P1D'));
        }

        return $planDays;
    }

    private function selectRecipesForDay(\DateTime $date, array &$recipePool, int $servings, string $locale, array $selectedIngredients = [], string $units = 'metric'): array
    {
        $season = $this->getSeasonForDate($date);
        $dayOfWeek = (int) $date->format('N'); // 1 (lundi) à 7 (dimanche)

        // Prioriser les recettes de saison
        $seasonalRecipes = array_filter($recipePool, function(Recipe $recipe) use ($season) {
            return in_array($season, $recipe->getSeason());
        });

        // Si pas assez de recettes de saison, utiliser toutes les recettes
        if (count($seasonalRecipes) < 3) {
            $seasonalRecipes = $recipePool;
        }

        // Prioriser les recettes avec les ingrédients sélectionnés
        if (!empty($selectedIngredients)) {
            $seasonalRecipes = $this->prioritizeRecipesByIngredients($seasonalRecipes, $selectedIngredients);
        }

        // Sélectionner les repas selon le jour de la semaine
        $selectedRecipes = [];

        // Petit-déjeuner
        $breakfast = $this->selectBreakfast($seasonalRecipes, $dayOfWeek);
        $selectedRecipes['breakfast'] = $breakfast;

        // Déjeuner
        $lunch = $this->selectLunch($seasonalRecipes, $dayOfWeek, $breakfast);
        $selectedRecipes['lunch'] = $lunch;

        // Dîner
        $dinner = $this->selectDinner($seasonalRecipes, $dayOfWeek, $breakfast, $lunch);
        $selectedRecipes['dinner'] = $dinner;

        // Retirer les recettes sélectionnées du pool pour éviter les répétitions
        $this->removeFromPool($recipePool, [$breakfast, $lunch, $dinner]);

        return $selectedRecipes;
    }

    private function prioritizeRecipesByIngredients(array $recipes, array $selectedIngredients): array
    {
        $prioritized = [];
        
        foreach ($recipes as $recipe) {
            $score = 0;
            $recipeIngredients = $recipe->getIngredients();
            
            foreach ($recipeIngredients as $ingredient) {
                $ingredientName = strtolower($ingredient->getName('fr'));
                
                foreach ($selectedIngredients as $selectedIngredient) {
                    if (strpos($ingredientName, strtolower($selectedIngredient)) !== false) {
                        $score++;
                    }
                }
            }
            
            $prioritized[] = [
                'recipe' => $recipe,
                'score' => $score
            ];
        }
        
        // Trier par score décroissant
        usort($prioritized, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Retourner seulement les recettes
        return array_map(function($item) {
            return $item['recipe'];
        }, $prioritized);
    }

    private function selectBreakfast(array $recipes, int $dayOfWeek): Recipe
    {
        // Filtrer les recettes de petit-déjeuner
        $breakfastRecipes = array_filter($recipes, function(Recipe $recipe) {
            $category = $recipe->getCategory();
            $tags = $recipe->getTags();
            
            return $category === 'petit_dejeuner' || 
                   in_array('petit_dejeuner', $tags) || 
                   in_array('breakfast', $tags);
        });

        if (empty($breakfastRecipes)) {
            // Si pas de recettes de petit-déjeuner, prendre une recette légère
            $lightRecipes = array_filter($recipes, function(Recipe $recipe) {
                $nutrition = $recipe->getNutrition();
                return isset($nutrition['calories']) && $nutrition['calories'] <= 300;
            });
            
            if (!empty($lightRecipes)) {
                return $this->selectRandomRecipe($lightRecipes);
            }
        }

        return $this->selectRandomRecipe($breakfastRecipes ?: $recipes);
    }

    private function selectLunch(array $recipes, int $dayOfWeek, Recipe $breakfast): Recipe
    {
        // Éviter de répéter le petit-déjeuner
        $availableRecipes = array_filter($recipes, function(Recipe $recipe) use ($breakfast) {
            return $recipe->getId() !== $breakfast->getId();
        });

        // Prioriser les plats principaux
        $mainDishRecipes = array_filter($availableRecipes, function(Recipe $recipe) {
            $category = $recipe->getCategory();
            return $category === 'plat_principal' || $category === 'main_dish';
        });

        if (!empty($mainDishRecipes)) {
            return $this->selectRandomRecipe($mainDishRecipes);
        }

        return $this->selectRandomRecipe($availableRecipes);
    }

    private function selectDinner(array $recipes, int $dayOfWeek, Recipe $breakfast, Recipe $lunch): Recipe
    {
        // Éviter de répéter les repas précédents
        $availableRecipes = array_filter($recipes, function(Recipe $recipe) use ($breakfast, $lunch) {
            return $recipe->getId() !== $breakfast->getId() && $recipe->getId() !== $lunch->getId();
        });

        // Pour le dîner, prioriser les recettes légères
        $lightRecipes = array_filter($availableRecipes, function(Recipe $recipe) {
            $nutrition = $recipe->getNutrition();
            return isset($nutrition['calories']) && $nutrition['calories'] <= 400;
        });

        if (!empty($lightRecipes)) {
            return $this->selectRandomRecipe($lightRecipes);
        }

        return $this->selectRandomRecipe($availableRecipes);
    }

    private function selectRandomRecipe(array $recipes): Recipe
    {
        if (empty($recipes)) {
            throw new \Exception('Aucune recette disponible');
        }

        $randomIndex = array_rand($recipes);
        return $recipes[$randomIndex];
    }

    private function removeFromPool(array &$recipePool, array $selectedRecipes): void
    {
        foreach ($selectedRecipes as $recipe) {
            $recipePool = array_filter($recipePool, function(Recipe $r) use ($recipe) {
                return $r->getId() !== $recipe->getId();
            });
        }
    }

    private function getSeasonForDate(\DateTime $date): string
    {
        $month = (int) $date->format('n');
        
        if ($month >= 3 && $month <= 5) {
            return 'printemps';
        } elseif ($month >= 6 && $month <= 8) {
            return 'ete';
        } elseif ($month >= 9 && $month <= 11) {
            return 'automne';
        } else {
            return 'hiver';
        }
    }

    public function calculateNutritionalBalance(MealPlan $plan): array
    {
        $totalCalories = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFat = 0;
        $totalFiber = 0;

        foreach ($plan->getDays() as $day) {
            foreach ($day->getMeals() as $meal) {
                $nutrition = $meal->getNutrition();
                
                $totalCalories += $nutrition['calories'] ?? 0;
                $totalProtein += $nutrition['protein'] ?? 0;
                $totalCarbs += $nutrition['carbs'] ?? 0;
                $totalFat += $nutrition['fat'] ?? 0;
                $totalFiber += $nutrition['fiber'] ?? 0;
            }
        }

        return [
            'total_calories' => $totalCalories,
            'total_protein' => $totalProtein,
            'total_carbs' => $totalCarbs,
            'total_fat' => $totalFat,
            'total_fiber' => $totalFiber,
            'average_calories_per_day' => $totalCalories / count($plan->getDays()),
            'average_protein_per_day' => $totalProtein / count($plan->getDays()),
            'average_carbs_per_day' => $totalCarbs / count($plan->getDays()),
            'average_fat_per_day' => $totalFat / count($plan->getDays())
        ];
    }

    public function generateShoppingList(MealPlan $plan, string $units = 'metric'): array
    {
        $shoppingList = [];
        $ingredients = [];

        // Collecter tous les ingrédients
        foreach ($plan->getDays() as $day) {
            foreach ($day->getMeals() as $meal) {
                $mealIngredients = $meal->getIngredients();
                foreach ($mealIngredients as $ingredient) {
                    $ingredientName = $ingredient->getName('fr');
                    $quantity = $this->unitService->convertQuantity($ingredient->getQuantity('metric'), $units);
                    
                    if (!isset($ingredients[$ingredientName])) {
                        $ingredients[$ingredientName] = [
                            'name' => $ingredientName,
                            'quantity' => $quantity,
                            'category' => $this->categorizeIngredient($ingredientName)
                        ];
                    } else {
                        // Additionner les quantités
                        $ingredients[$ingredientName]['quantity'] = $this->unitService->addQuantities(
                            $ingredients[$ingredientName]['quantity'],
                            $quantity
                        );
                    }
                }
            }
        }

        // Organiser par catégorie
        foreach ($ingredients as $ingredient) {
            $category = $ingredient['category'];
            if (!isset($shoppingList[$category])) {
                $shoppingList[$category] = [];
            }
            $shoppingList[$category][] = $ingredient;
        }

        return $shoppingList;
    }

    private function categorizeIngredient(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);
        
        $categories = [
            'Fruits et légumes' => ['tomate', 'carotte', 'pomme', 'banane', 'salade', 'épinard', 'brocoli', 'courgette', 'aubergine', 'poivron', 'concombre', 'oignon', 'ail', 'citron', 'orange', 'fraise', 'framboise', 'myrtille', 'pêche', 'abricot', 'prune', 'raisin', 'pomme', 'poire', 'kiwi', 'ananas', 'mangue', 'avocat'],
            'Viandes et poissons' => ['poulet', 'boeuf', 'porc', 'agneau', 'saumon', 'thon', 'cabillaud', 'sardine', 'crevette', 'moule', 'huître'],
            'Produits laitiers' => ['lait', 'fromage', 'yaourt', 'crème', 'beurre', 'fromage blanc'],
            'Céréales et féculents' => ['riz', 'pâtes', 'quinoa', 'boulgour', 'couscous', 'pain', 'farine', 'semoule'],
            'Épices et condiments' => ['sel', 'poivre', 'curry', 'paprika', 'cumin', 'cannelle', 'vanille', 'basilic', 'thym', 'romarin', 'origan'],
            'Huiles et matières grasses' => ['huile d\'olive', 'huile de tournesol', 'huile de colza', 'beurre', 'margarine'],
            'Autres' => []
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($ingredientName, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'Autres';
    }

    public function getDietTypes(): array
    {
        return [
            'prise_masse' => 'Prise de masse',
            'equilibre' => 'Équilibré',
            'seche' => 'Sèche',
            'anti_cholesterol' => 'Anti-cholestérol',
            'vegan' => 'Vegan',
            'vegetarien' => 'Végétarien',
            'recettes_simples' => 'Recettes simples',
            'cetogene' => 'Cétogène',
            'paleo' => 'Paléo',
            'sans_gluten' => 'Sans gluten',
            'mediterraneen' => 'Méditerranéen'
        ];
    }

    public function getAllergens(): array
    {
        return [
            'gluten' => 'Gluten',
            'lactose' => 'Lactose',
            'fruits_coque' => 'Fruits à coque',
            'oeufs' => 'Œufs',
            'soja' => 'Soja',
            'poisson' => 'Poisson',
            'crustaces' => 'Crustacés',
            'arachides' => 'Arachides',
            'moutarde' => 'Moutarde',
            'celeri' => 'Céleri',
            'sulfites' => 'Sulfites',
            'lupin' => 'Lupin',
            'mollusques' => 'Mollusques'
        ];
    }

    public function getPeriods(): array
    {
        return [
            'week' => '1 semaine',
            'month' => '1 mois',
            'year' => '1 année'
        ];
    }

    public function exportToPdf(MealPlan $plan, string $locale = 'fr'): string
    {
        // Cette méthode sera implémentée dans le service PdfExportService
        return '';
    }

    public function savePlan(MealPlan $plan, int $userId): bool
    {
        try {
            // Sauvegarder le planning en base de données
            // Cette méthode sera implémentée dans le repository
            return true;
        } catch (\Exception $e) {
            error_log('Erreur lors de la sauvegarde du planning: ' . $e->getMessage());
            return false;
        }
    }

    public function getUserPlans(int $userId, string $locale = 'fr'): array
    {
        try {
            // Récupérer les plannings de l'utilisateur
            // Cette méthode sera implémentée dans le repository
            return [];
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des plannings: ' . $e->getMessage());
            return [];
        }
    }

    public function getPlan(string $planId, int $userId, string $locale = 'fr'): ?MealPlan
    {
        try {
            // Récupérer un planning spécifique
            // Cette méthode sera implémentée dans le repository
            return null;
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération du planning: ' . $e->getMessage());
            return null;
        }
    }
}
