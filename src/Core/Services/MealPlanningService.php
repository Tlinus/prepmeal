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

    public function __construct(
        RecipeRepository $recipeRepository,
        SeasonalIngredientService $seasonalService
    ) {
        $this->recipeRepository = $recipeRepository;
        $this->seasonalService = $seasonalService;
    }

    public function generatePlan(array $preferences): MealPlan
    {
        $period = $preferences['period'] ?? 'week';
        $dietType = $preferences['diet_type'] ?? 'equilibre';
        $allergens = $preferences['allergens'] ?? [];
        $maxPrepTime = $preferences['max_prep_time'] ?? null;
        $servings = $preferences['servings'] ?? 2;
        $locale = $preferences['locale'] ?? 'fr';

        // Calculer la période
        $startDate = new \DateTime();
        $endDate = $this->calculateEndDate($startDate, $period);

        // Récupérer les recettes disponibles
        $availableRecipes = $this->getAvailableRecipes($dietType, $allergens, $maxPrepTime);

        // Générer le planning
        $planDays = $this->generatePlanDays($startDate, $endDate, $availableRecipes, $servings, $locale);

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

    private function getAvailableRecipes(string $dietType, array $allergens, ?int $maxPrepTime): array
    {
        $filters = [
            'diet_type' => $dietType,
            'allergens' => $allergens
        ];

        if ($maxPrepTime) {
            $filters['max_prep_time'] = $maxPrepTime;
        }

        return $this->recipeRepository->findAll($filters);
    }

    private function generatePlanDays(\DateTime $startDate, \DateTime $endDate, array $recipes, int $servings, string $locale): array
    {
        $planDays = [];
        $currentDate = clone $startDate;
        $recipePool = $recipes;

        while ($currentDate <= $endDate) {
            $dayRecipes = $this->selectRecipesForDay($currentDate, $recipePool, $servings, $locale);
            
            $planDays[] = new MealPlanDay(
                $currentDate,
                $dayRecipes
            );

            $currentDate->add(new \DateInterval('P1D'));
        }

        return $planDays;
    }

    private function selectRecipesForDay(\DateTime $date, array &$recipePool, int $servings, string $locale): array
    {
        $season = $this->getSeasonForDate($date);
        $dayOfWeek = $date->format('N'); // 1 (lundi) à 7 (dimanche)

        // Prioriser les recettes de saison
        $seasonalRecipes = array_filter($recipePool, function(Recipe $recipe) use ($season) {
            return in_array($season, $recipe->getSeason());
        });

        // Si pas assez de recettes de saison, utiliser toutes les recettes
        if (count($seasonalRecipes) < 3) {
            $seasonalRecipes = $recipePool;
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

    private function selectBreakfast(array $recipes, int $dayOfWeek): Recipe
    {
        // Filtrer les recettes de petit-déjeuner
        $breakfastRecipes = array_filter($recipes, function(Recipe $recipe) {
            return $recipe->getCategory() === 'petit_dejeuner' || 
                   in_array('rapide', $recipe->getTags());
        });

        if (empty($breakfastRecipes)) {
            $breakfastRecipes = $recipes;
        }

        // Prioriser les recettes rapides en semaine
        if ($dayOfWeek <= 5) {
            $quickRecipes = array_filter($breakfastRecipes, function(Recipe $recipe) {
                return $recipe->getTotalTime() <= 15;
            });
            if (!empty($quickRecipes)) {
                $breakfastRecipes = $quickRecipes;
            }
        }

        return $this->selectRandomRecipe($breakfastRecipes);
    }

    private function selectLunch(array $recipes, int $dayOfWeek, Recipe $breakfast): Recipe
    {
        // Éviter les répétitions avec le petit-déjeuner
        $lunchRecipes = array_filter($recipes, function(Recipe $recipe) use ($breakfast) {
            return $recipe->getId() !== $breakfast->getId();
        });

        // Prioriser les recettes équilibrées pour le déjeuner
        $balancedRecipes = array_filter($lunchRecipes, function(Recipe $recipe) {
            return $recipe->getCategory() === 'equilibre' || 
                   in_array('sain', $recipe->getTags());
        });

        if (!empty($balancedRecipes)) {
            $lunchRecipes = $balancedRecipes;
        }

        return $this->selectRandomRecipe($lunchRecipes);
    }

    private function selectDinner(array $recipes, int $dayOfWeek, Recipe $breakfast, Recipe $lunch): Recipe
    {
        // Éviter les répétitions
        $dinnerRecipes = array_filter($recipes, function(Recipe $recipe) use ($breakfast, $lunch) {
            return $recipe->getId() !== $breakfast->getId() && 
                   $recipe->getId() !== $lunch->getId();
        });

        // Prioriser les recettes légères pour le dîner
        $lightRecipes = array_filter($dinnerRecipes, function(Recipe $recipe) {
            return $recipe->getCategory() === 'leger' || 
                   in_array('leger', $recipe->getTags());
        });

        if (!empty($lightRecipes)) {
            $dinnerRecipes = $lightRecipes;
        }

        return $this->selectRandomRecipe($dinnerRecipes);
    }

    private function selectRandomRecipe(array $recipes): Recipe
    {
        if (empty($recipes)) {
            throw new \RuntimeException('Aucune recette disponible');
        }

        return $recipes[array_rand($recipes)];
    }

    private function removeFromPool(array &$recipePool, array $selectedRecipes): void
    {
        $selectedIds = array_map(fn(Recipe $recipe) => $recipe->getId(), $selectedRecipes);
        
        $recipePool = array_filter($recipePool, function(Recipe $recipe) use ($selectedIds) {
            return !in_array($recipe->getId(), $selectedIds);
        });

        // Si le pool est vide, le remplir à nouveau
        if (empty($recipePool)) {
            $recipePool = $this->recipeRepository->findAll();
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
        $totalNutrition = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fat' => 0,
            'fiber' => 0
        ];

        foreach ($plan->getDays() as $day) {
            foreach ($day->getMeals() as $meal) {
                $nutrition = $meal->getNutrition();
                foreach ($totalNutrition as $key => $value) {
                    $totalNutrition[$key] += $nutrition[$key] ?? 0;
                }
            }
        }

        $daysCount = count($plan->getDays());
        foreach ($totalNutrition as $key => $value) {
            $totalNutrition[$key] = round($value / $daysCount, 1);
        }

        return $totalNutrition;
    }

    public function generateShoppingList(MealPlan $plan): array
    {
        $shoppingList = [];

        foreach ($plan->getDays() as $day) {
            foreach ($day->getMeals() as $meal) {
                foreach ($meal->getIngredients() as $ingredient) {
                    $name = $ingredient->getName();
                    $quantity = $ingredient->getQuantity();

                    if (!isset($shoppingList[$name])) {
                        $shoppingList[$name] = [
                            'quantity' => $quantity,
                            'seasonal' => $ingredient->isSeasonal(),
                            'season' => $ingredient->getSeason()
                        ];
                    } else {
                        // Additionner les quantités
                        $shoppingList[$name]['quantity']['metric']['amount'] += $quantity['metric']['amount'];
                        if (isset($quantity['imperial'])) {
                            $shoppingList[$name]['quantity']['imperial']['amount'] += $quantity['imperial']['amount'];
                        }
                    }
                }
            }
        }

        return $shoppingList;
    }
}
