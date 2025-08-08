<?php

declare(strict_types=1);

namespace PrepMeal\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PrepMeal\Core\Services\MealPlanningService;
use PrepMeal\Core\Services\SeasonalIngredientService;
use PrepMeal\Core\Database\RecipeRepository;
use PrepMeal\Core\Models\Recipe;
use PrepMeal\Core\Models\MealPlan;

class MealPlanningServiceTest extends TestCase
{
    private MealPlanningService $mealPlanningService;
    private RecipeRepository $recipeRepository;
    private SeasonalIngredientService $seasonalService;

    protected function setUp(): void
    {
        $this->recipeRepository = $this->createMock(RecipeRepository::class);
        $this->seasonalService = $this->createMock(SeasonalIngredientService::class);
        $this->mealPlanningService = new MealPlanningService($this->recipeRepository, $this->seasonalService);
    }

    public function testGeneratePlanWithValidPreferences(): void
    {
        // Arrange
        $preferences = [
            'period' => 'week',
            'diet_type' => 'equilibre',
            'allergens' => [],
            'servings' => 2,
            'locale' => 'fr'
        ];

        $mockRecipes = [
            $this->createMockRecipe('recipe_001', 'equilibre'),
            $this->createMockRecipe('recipe_002', 'equilibre'),
            $this->createMockRecipe('recipe_003', 'equilibre')
        ];

        $this->recipeRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($mockRecipes);

        // Act
        $plan = $this->mealPlanningService->generatePlan($preferences);

        // Assert
        $this->assertInstanceOf(MealPlan::class, $plan);
        $this->assertNotEmpty($plan->getDays());
        $this->assertEquals('week', $preferences['period']);
    }

    public function testGeneratePlanWithInvalidPeriod(): void
    {
        // Arrange
        $preferences = [
            'period' => 'invalid_period',
            'diet_type' => 'equilibre',
            'allergens' => [],
            'servings' => 2,
            'locale' => 'fr'
        ];

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->mealPlanningService->generatePlan($preferences);
    }

    public function testGeneratePlanWithInvalidDietType(): void
    {
        // Arrange
        $preferences = [
            'period' => 'week',
            'diet_type' => 'invalid_diet',
            'allergens' => [],
            'servings' => 2,
            'locale' => 'fr'
        ];

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->mealPlanningService->generatePlan($preferences);
    }

    public function testCalculateNutritionalBalance(): void
    {
        // Arrange
        $plan = $this->createMockMealPlan();

        // Act
        $balance = $this->mealPlanningService->calculateNutritionalBalance($plan);

        // Assert
        $this->assertIsArray($balance);
        $this->assertArrayHasKey('total_calories', $balance);
        $this->assertArrayHasKey('total_protein', $balance);
        $this->assertArrayHasKey('total_carbs', $balance);
        $this->assertArrayHasKey('total_fat', $balance);
    }

    public function testGenerateShoppingList(): void
    {
        // Arrange
        $plan = $this->createMockMealPlan();

        // Act
        $shoppingList = $this->mealPlanningService->generateShoppingList($plan);

        // Assert
        $this->assertIsArray($shoppingList);
        $this->assertNotEmpty($shoppingList);
    }

    public function testGetDietTypes(): void
    {
        // Act
        $dietTypes = $this->mealPlanningService->getDietTypes();

        // Assert
        $this->assertIsArray($dietTypes);
        $this->assertContains('equilibre', $dietTypes);
        $this->assertContains('vegan', $dietTypes);
        $this->assertContains('vegetarien', $dietTypes);
    }

    public function testGetAllergens(): void
    {
        // Act
        $allergens = $this->mealPlanningService->getAllergens();

        // Assert
        $this->assertIsArray($allergens);
        $this->assertContains('gluten', $allergens);
        $this->assertContains('lactose', $allergens);
        $this->assertContains('fruits_coque', $allergens);
    }

    public function testGetPeriods(): void
    {
        // Act
        $periods = $this->mealPlanningService->getPeriods();

        // Assert
        $this->assertIsArray($periods);
        $this->assertContains('week', $periods);
        $this->assertContains('month', $periods);
        $this->assertContains('year', $periods);
    }

    private function createMockRecipe(string $id, string $category): Recipe
    {
        $recipe = $this->createMock(Recipe::class);
        $recipe->method('getId')->willReturn($id);
        $recipe->method('getCategory')->willReturn($category);
        $recipe->method('getTitle')->willReturn('Test Recipe');
        $recipe->method('getDescription')->willReturn('Test Description');
        $recipe->method('getNutrition')->willReturn([
            'calories' => 300,
            'protein' => 15,
            'carbs' => 40,
            'fat' => 10
        ]);
        $recipe->method('getIngredients')->willReturn([]);
        $recipe->method('getAllergens')->willReturn([]);
        $recipe->method('getDietaryRestrictions')->willReturn([]);

        return $recipe;
    }

    private function createMockMealPlan(): MealPlan
    {
        $plan = $this->createMock(MealPlan::class);
        $plan->method('getDays')->willReturn([]);
        $plan->method('getId')->willReturn('test_plan_001');

        return $plan;
    }
}
