<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\Unit\TestCase;
use App\Core\Services\MealPlanningService;
use Psr\Log\LoggerInterface;

class MealPlanningServiceTest extends TestCase
{
    private MealPlanningService $mealPlanningService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mealPlanningService = new MealPlanningService($this->logger);
    }

    public function testFilterRecipesByDietType(): void
    {
        $recipes = [
            $this->createMockRecipe(['category' => 'equilibre', 'nutrition' => ['calories' => 320, 'protein' => 12]]),
            $this->createMockRecipe(['category' => 'prise_masse', 'nutrition' => ['calories' => 650, 'protein' => 35]]),
            $this->createMockRecipe(['category' => 'seche', 'nutrition' => ['calories' => 280, 'protein' => 25, 'fat' => 8]]),
            $this->createMockRecipe(['category' => 'anti_cholesterol', 'nutrition' => ['calories' => 300, 'fat' => 5]]),
            $this->createMockRecipe(['category' => 'vegan', 'dietary_restrictions' => ['vegan']]),
            $this->createMockRecipe(['category' => 'vegetarien', 'dietary_restrictions' => ['vegetarien']]),
            $this->createMockRecipe(['category' => 'recettes_simples', 'ingredients' => [['name' => ['fr' => 'pomme']]], 'prep_time' => 10]),
            $this->createMockRecipe(['category' => 'keto', 'nutrition' => ['carbs' => 15, 'fat' => 25]]),
            $this->createMockRecipe(['category' => 'paleo', 'ingredients' => [['name' => ['fr' => 'viande']]]]),
            $this->createMockRecipe(['category' => 'mediterranean', 'ingredients' => [['name' => ['fr' => 'huile_olive']]])
        ];

        // Test prise de masse
        $priseMasseRecipes = $this->mealPlanningService->filterRecipesByDietType($recipes, 'prise_masse');
        $this->assertNotEmpty($priseMasseRecipes);
        foreach ($priseMasseRecipes as $recipe) {
            $this->assertGreaterThanOrEqual(500, $recipe['nutrition']['calories']);
            $this->assertGreaterThanOrEqual(20, $recipe['nutrition']['protein']);
        }

        // Test sèche
        $secheRecipes = $this->mealPlanningService->filterRecipesByDietType($recipes, 'seche');
        $this->assertNotEmpty($secheRecipes);
        foreach ($secheRecipes as $recipe) {
            $this->assertLessThanOrEqual(400, $recipe['nutrition']['calories']);
            $this->assertGreaterThanOrEqual(20, $recipe['nutrition']['protein']);
        }

        // Test anti-cholestérol
        $antiCholesterolRecipes = $this->mealPlanningService->filterRecipesByDietType($recipes, 'anti_cholesterol');
        $this->assertNotEmpty($antiCholesterolRecipes);
        foreach ($antiCholesterolRecipes as $recipe) {
            $this->assertLessThanOrEqual(10, $recipe['nutrition']['fat']);
        }

        // Test vegan
        $veganRecipes = $this->mealPlanningService->filterRecipesByDietType($recipes, 'vegan');
        $this->assertNotEmpty($veganRecipes);
        foreach ($veganRecipes as $recipe) {
            $this->assertContains('vegan', $recipe['dietary_restrictions']);
        }

        // Test recettes simples
        $simpleRecipes = $this->mealPlanningService->filterRecipesByDietType($recipes, 'recettes_simples');
        $this->assertNotEmpty($simpleRecipes);
        foreach ($simpleRecipes as $recipe) {
            $this->assertLessThanOrEqual(5, count($recipe['ingredients']));
            $this->assertLessThanOrEqual(30, $recipe['prep_time']);
        }

        // Test keto
        $ketoRecipes = $this->mealPlanningService->filterRecipesByDietType($recipes, 'keto');
        $this->assertNotEmpty($ketoRecipes);
        foreach ($ketoRecipes as $recipe) {
            $this->assertLessThanOrEqual(20, $recipe['nutrition']['carbs']);
            $this->assertGreaterThanOrEqual(20, $recipe['nutrition']['fat']);
        }
    }

    public function testFilterRecipesByAllergens(): void
    {
        $recipes = [
            $this->createMockRecipe(['allergens' => ['gluten']]),
            $this->createMockRecipe(['allergens' => ['lactose']]),
            $this->createMockRecipe(['allergens' => ['gluten', 'lactose']]),
            $this->createMockRecipe(['allergens' => []]),
        ];

        $excludedAllergens = ['gluten'];
        $filteredRecipes = $this->mealPlanningService->filterRecipesByAllergens($recipes, $excludedAllergens);

        $this->assertNotEmpty($filteredRecipes);
        foreach ($filteredRecipes as $recipe) {
            $this->assertNotContains('gluten', $recipe['allergens']);
        }

        // Vérifier que les recettes sans allergènes sont incluses
        $hasNoAllergens = false;
        foreach ($filteredRecipes as $recipe) {
            if (empty($recipe['allergens'])) {
                $hasNoAllergens = true;
                break;
            }
        }
        $this->assertTrue($hasNoAllergens);
    }

    public function testFilterRecipesByIngredients(): void
    {
        $recipes = [
            $this->createMockRecipe(['ingredients' => [
                ['name' => ['fr' => 'pomme'], 'seasonal' => true, 'season' => ['ete']],
                ['name' => ['fr' => 'banane'], 'seasonal' => false]
            ]]),
            $this->createMockRecipe(['ingredients' => [
                ['name' => ['fr' => 'orange'], 'seasonal' => true, 'season' => ['hiver']],
                ['name' => ['fr' => 'pomme'], 'seasonal' => true, 'season' => ['ete']]
            ]]),
            $this->createMockRecipe(['ingredients' => [
                ['name' => ['fr' => 'carotte'], 'seasonal' => true, 'season' => ['printemps']]
            ]])
        ];

        $selectedIngredients = ['pomme', 'orange'];
        $filteredRecipes = $this->mealPlanningService->filterRecipesByIngredients($recipes, $selectedIngredients);

        $this->assertNotEmpty($filteredRecipes);
        foreach ($filteredRecipes as $recipe) {
            $hasSelectedIngredient = false;
            foreach ($recipe['ingredients'] as $ingredient) {
                if (in_array($ingredient['name']['fr'], $selectedIngredients)) {
                    $hasSelectedIngredient = true;
                    break;
                }
            }
            $this->assertTrue($hasSelectedIngredient, 'Recipe should contain at least one selected ingredient');
        }
    }

    public function testFilterRecipesByIngredientsWithEmptySelection(): void
    {
        $recipes = [
            $this->createMockRecipe(['ingredients' => [['name' => ['fr' => 'pomme']]]]),
            $this->createMockRecipe(['ingredients' => [['name' => ['fr' => 'banane']]]])
        ];

        $filteredRecipes = $this->mealPlanningService->filterRecipesByIngredients($recipes, []);
        
        // Si aucun ingrédient n'est sélectionné, toutes les recettes doivent être retournées
        $this->assertEquals(count($recipes), count($filteredRecipes));
    }

    public function testSelectBreakfast(): void
    {
        $recipes = [
            $this->createMockRecipe(['tags' => ['petit_dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['petit_dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['dejeuner'], 'category' => 'equilibre'])
        ];

        $selectedRecipe = $this->mealPlanningService->selectBreakfast($recipes);
        
        $this->assertNotNull($selectedRecipe);
        $this->assertContains('petit_dejeuner', $selectedRecipe['tags']);
    }

    public function testSelectLunch(): void
    {
        $recipes = [
            $this->createMockRecipe(['tags' => ['dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['petit_dejeuner'], 'category' => 'equilibre'])
        ];

        $selectedRecipe = $this->mealPlanningService->selectLunch($recipes);
        
        $this->assertNotNull($selectedRecipe);
        $this->assertContains('dejeuner', $selectedRecipe['tags']);
    }

    public function testSelectDinner(): void
    {
        $recipes = [
            $this->createMockRecipe(['tags' => ['diner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['diner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['dejeuner'], 'category' => 'equilibre'])
        ];

        $selectedRecipe = $this->mealPlanningService->selectDinner($recipes);
        
        $this->assertNotNull($selectedRecipe);
        $this->assertContains('diner', $selectedRecipe['tags']);
    }

    public function testRemoveFromPool(): void
    {
        $recipes = [
            $this->createMockRecipe(['id' => 'recipe_1']),
            $this->createMockRecipe(['id' => 'recipe_2']),
            $this->createMockRecipe(['id' => 'recipe_3'])
        ];

        $pool = $recipes;
        $this->mealPlanningService->removeFromPool($pool, 'recipe_2');

        $this->assertEquals(2, count($pool));
        $this->assertNotContains('recipe_2', array_column($pool, 'id'));
    }

    public function testCalculateNutritionalBalance(): void
    {
        $meals = [
            [
                'recipe' => $this->createMockRecipe(['nutrition' => ['calories' => 300, 'protein' => 15, 'carbs' => 40, 'fat' => 10]]),
                'type' => 'breakfast'
            ],
            [
                'recipe' => $this->createMockRecipe(['nutrition' => ['calories' => 500, 'protein' => 25, 'carbs' => 60, 'fat' => 15]]),
                'type' => 'lunch'
            ],
            [
                'recipe' => $this->createMockRecipe(['nutrition' => ['calories' => 400, 'protein' => 20, 'carbs' => 50, 'fat' => 12]]),
                'type' => 'dinner'
            ]
        ];

        $balance = $this->mealPlanningService->calculateNutritionalBalance($meals);

        $this->assertArrayHasKeys(['total', 'daily_average'], $balance);
        $this->assertEquals(1200, $balance['total']['calories']);
        $this->assertEquals(60, $balance['total']['protein']);
        $this->assertEquals(150, $balance['total']['carbs']);
        $this->assertEquals(37, $balance['total']['fat']);

        $this->assertEquals(400, $balance['daily_average']['calories']);
        $this->assertEquals(20, $balance['daily_average']['protein']);
        $this->assertEquals(50, $balance['daily_average']['carbs']);
        $this->assertEquals(12.33, $balance['daily_average']['fat'], '', 0.01);
    }

    public function testGenerateShoppingList(): void
    {
        $meals = [
            [
                'recipe' => $this->createMockRecipe(['ingredients' => [
                    ['name' => ['fr' => 'pomme'], 'quantity' => ['metric' => ['amount' => 2, 'unit' => 'pièces']]],
                    ['name' => ['fr' => 'banane'], 'quantity' => ['metric' => ['amount' => 3, 'unit' => 'pièces']]]
                ]]),
                'type' => 'breakfast'
            ],
            [
                'recipe' => $this->createMockRecipe(['ingredients' => [
                    ['name' => ['fr' => 'pomme'], 'quantity' => ['metric' => ['amount' => 1, 'unit' => 'pièces']]],
                    ['name' => ['fr' => 'carotte'], 'quantity' => ['metric' => ['amount' => 200, 'unit' => 'g']]]
                ]]),
                'type' => 'lunch'
            ]
        ];

        $shoppingList = $this->mealPlanningService->generateShoppingList($meals);

        // Vérifier que la structure est organisée par catégorie
        $this->assertIsArray($shoppingList);
        $this->assertNotEmpty($shoppingList);
        
        // Vérifier que les catégories existent
        $this->assertArrayHasKey('Fruits et légumes', $shoppingList);
        $this->assertArrayHasKey('Autres', $shoppingList);
        
        // Vérifier que les ingrédients sont dans les bonnes catégories
        $this->assertNotEmpty($shoppingList['Fruits et légumes']);
        $this->assertNotEmpty($shoppingList['Autres']);
        
        // Vérifier que les quantités sont additionnées pour la pomme
        $pommeItem = null;
        foreach ($shoppingList['Fruits et légumes'] as $item) {
            if ($item['name'] === 'pomme') {
                $pommeItem = $item;
                break;
            }
        }
        
        $this->assertNotNull($pommeItem);
        $this->assertEquals(3, $pommeItem['quantity']['amount']); // 2 + 1
    }

    public function testCategorizeIngredient(): void
    {
        $reflection = new \ReflectionClass($this->mealPlanningService);
        $method = $reflection->getMethod('categorizeIngredient');
        $method->setAccessible(true);

        $this->assertEquals('Fruits et légumes', $method->invoke($this->mealPlanningService, 'pomme'));
        $this->assertEquals('Fruits et légumes', $method->invoke($this->mealPlanningService, 'carotte'));
        $this->assertEquals('Viandes et poissons', $method->invoke($this->mealPlanningService, 'poulet'));
        $this->assertEquals('Produits laitiers', $method->invoke($this->mealPlanningService, 'lait'));
        $this->assertEquals('Céréales et féculents', $method->invoke($this->mealPlanningService, 'riz'));
        $this->assertEquals('Épices et condiments', $method->invoke($this->mealPlanningService, 'sel'));
        $this->assertEquals('Autres', $method->invoke($this->mealPlanningService, 'ingredient_inconnu'));
    }

    public function testGenerateMealPlan(): void
    {
        $recipes = [
            $this->createMockRecipe(['tags' => ['petit_dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['diner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['petit_dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['diner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['petit_dejeuner'], 'category' => 'equilibre'])
        ];

        $preferences = [
            'diet_type' => 'equilibre',
            'excluded_allergens' => [],
            'selected_ingredients' => [],
            'period' => 'week',
            'servings' => 2
        ];

        $plan = $this->mealPlanningService->generateMealPlan($recipes, $preferences);

        $this->assertArrayHasKeys(['days', 'nutritional_balance', 'shopping_list'], $plan);
        $this->assertEquals(7, count($plan['days']));

        foreach ($plan['days'] as $day) {
            $this->assertArrayHasKeys(['date', 'meals'], $day);
            $this->assertEquals(3, count($day['meals'])); // breakfast, lunch, dinner
            
            foreach ($day['meals'] as $meal) {
                $this->assertArrayHasKeys(['type', 'recipe'], $meal);
                $this->assertContains($meal['type'], ['breakfast', 'lunch', 'dinner']);
            }
        }
    }

    public function testGenerateMealPlanWithInsufficientRecipes(): void
    {
        $recipes = [
            $this->createMockRecipe(['tags' => ['petit_dejeuner'], 'category' => 'equilibre']),
            $this->createMockRecipe(['tags' => ['dejeuner'], 'category' => 'equilibre'])
        ];

        $preferences = [
            'diet_type' => 'equilibre',
            'excluded_allergens' => [],
            'selected_ingredients' => [],
            'period' => 'week',
            'servings' => 2
        ];

        $plan = $this->mealPlanningService->generateMealPlan($recipes, $preferences);

        // Le plan doit être généré même avec peu de recettes
        $this->assertArrayHasKeys(['days', 'nutritional_balance', 'shopping_list'], $plan);
        $this->assertEquals(7, count($plan['days']));
    }
}
