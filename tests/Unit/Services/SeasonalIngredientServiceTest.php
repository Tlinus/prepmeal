<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\Unit\TestCase;
use App\Core\Services\SeasonalIngredientService;

class SeasonalIngredientServiceTest extends TestCase
{
    private SeasonalIngredientService $seasonalIngredientService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seasonalIngredientService = new SeasonalIngredientService();
    }

    public function testGetCurrentSeasonIngredients(): void
    {
        $currentSeason = $this->seasonalIngredientService->getCurrentSeason();
        $ingredients = $this->seasonalIngredientService->getCurrentSeasonIngredients();

        $this->assertNotEmpty($ingredients);
        $this->assertIsArray($ingredients);

        // Vérifier que tous les ingrédients appartiennent à la saison actuelle
        foreach ($ingredients as $ingredient) {
            $this->assertArrayHasKey('name', $ingredient);
            $this->assertArrayHasKey('season', $ingredient);
            $this->assertContains($currentSeason, $ingredient['season']);
        }
    }

    public function testGetIngredientsForSpecificSeason(): void
    {
        $springIngredients = $this->seasonalIngredientService->getIngredientsForSeason('printemps');
        $this->assertNotEmpty($springIngredients);
        $this->assertIsArray($springIngredients);

        foreach ($springIngredients as $ingredient) {
            $this->assertArrayHasKey('name', $ingredient);
            $this->assertArrayHasKey('season', $ingredient);
            $this->assertContains('printemps', $ingredient['season']);
        }

        $summerIngredients = $this->seasonalIngredientService->getIngredientsForSeason('ete');
        $this->assertNotEmpty($summerIngredients);
        $this->assertIsArray($summerIngredients);

        foreach ($summerIngredients as $ingredient) {
            $this->assertArrayHasKey('name', $ingredient);
            $this->assertArrayHasKey('season', $ingredient);
            $this->assertContains('ete', $ingredient['season']);
        }
    }

    public function testGetIngredientsForAllSeasons(): void
    {
        $seasons = ['printemps', 'ete', 'automne', 'hiver'];
        
        foreach ($seasons as $season) {
            $ingredients = $this->seasonalIngredientService->getIngredientsForSeason($season);
            $this->assertNotEmpty($ingredients, "Season {$season} should have ingredients");
            $this->assertIsArray($ingredients);
            
            foreach ($ingredients as $ingredient) {
                $this->assertArrayHasKey('name', $ingredient);
                $this->assertArrayHasKey('season', $ingredient);
                $this->assertContains($season, $ingredient['season']);
            }
        }
    }

    public function testGetIngredientNameInLocale(): void
    {
        // Test avec la locale française (par défaut)
        $ingredients = $this->seasonalIngredientService->getCurrentSeasonIngredients();
        $firstIngredient = $ingredients[0];
        
        $name = $this->seasonalIngredientService->getIngredientName($firstIngredient, 'fr');
        $this->assertIsString($name);
        $this->assertNotEmpty($name);

        // Test avec la locale anglaise
        $name = $this->seasonalIngredientService->getIngredientName($firstIngredient, 'en');
        $this->assertIsString($name);
        $this->assertNotEmpty($name);

        // Test avec une locale non supportée (fallback vers le français)
        $name = $this->seasonalIngredientService->getIngredientName($firstIngredient, 'invalid_locale');
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    public function testGetAllIngredients(): void
    {
        $allIngredients = $this->seasonalIngredientService->getAllIngredients();
        
        $this->assertNotEmpty($allIngredients);
        $this->assertIsArray($allIngredients);

        // Vérifier que tous les ingrédients ont la structure attendue
        foreach ($allIngredients as $ingredient) {
            $this->assertArrayHasKey('name', $ingredient);
            $this->assertArrayHasKey('season', $ingredient);
            $this->assertIsArray($ingredient['name']);
            $this->assertIsArray($ingredient['season']);
            
            // Vérifier que l'ingrédient a au moins un nom en français
            $this->assertArrayHasKey('fr', $ingredient['name']);
            $this->assertNotEmpty($ingredient['name']['fr']);
            
            // Vérifier que l'ingrédient appartient à au moins une saison
            $this->assertNotEmpty($ingredient['season']);
        }
    }

    public function testGetIngredientsByCategory(): void
    {
        $categories = ['fruits', 'legumes', 'herbes', 'champignons'];
        
        foreach ($categories as $category) {
            $ingredients = $this->seasonalIngredientService->getIngredientsByCategory($category);
            
            if (!empty($ingredients)) {
                $this->assertIsArray($ingredients);
                
                foreach ($ingredients as $ingredient) {
                    $this->assertArrayHasKey('name', $ingredient);
                    $this->assertArrayHasKey('season', $ingredient);
                    $this->assertArrayHasKey('category', $ingredient);
                    $this->assertEquals($category, $ingredient['category']);
                }
            }
        }
    }

    public function testGetCurrentSeason(): void
    {
        $currentSeason = $this->seasonalIngredientService->getCurrentSeason();
        
        $this->assertIsString($currentSeason);
        $this->assertNotEmpty($currentSeason);
        $this->assertContains($currentSeason, ['printemps', 'ete', 'automne', 'hiver']);
    }

    public function testGetSeasonalIngredientsForMultipleSeasons(): void
    {
        $multiSeasonIngredients = $this->seasonalIngredientService->getIngredientsForMultipleSeasons(['printemps', 'ete']);
        
        $this->assertNotEmpty($multiSeasonIngredients);
        $this->assertIsArray($multiSeasonIngredients);
        
        foreach ($multiSeasonIngredients as $ingredient) {
            $this->assertArrayHasKey('name', $ingredient);
            $this->assertArrayHasKey('season', $ingredient);
            
            // Vérifier que l'ingrédient appartient à au moins une des saisons demandées
            $hasMatchingSeason = false;
            foreach (['printemps', 'ete'] as $season) {
                if (in_array($season, $ingredient['season'])) {
                    $hasMatchingSeason = true;
                    break;
                }
            }
            $this->assertTrue($hasMatchingSeason, "Ingredient should belong to at least one of the requested seasons");
        }
    }

    public function testGetIngredientsForInvalidSeason(): void
    {
        $ingredients = $this->seasonalIngredientService->getIngredientsForSeason('invalid_season');
        
        // Devrait retourner un tableau vide pour une saison invalide
        $this->assertIsArray($ingredients);
        $this->assertEmpty($ingredients);
    }

    public function testGetIngredientNameWithMissingLocale(): void
    {
        $ingredients = $this->seasonalIngredientService->getCurrentSeasonIngredients();
        $firstIngredient = $ingredients[0];
        
        // Modifier temporairement l'ingrédient pour enlever la locale française
        $modifiedIngredient = $firstIngredient;
        unset($modifiedIngredient['name']['fr']);
        
        // Devrait retourner le nom en anglais ou une chaîne vide
        $name = $this->seasonalIngredientService->getIngredientName($modifiedIngredient, 'fr');
        $this->assertIsString($name);
    }

    public function testGetSeasonalIngredientsCount(): void
    {
        $allIngredients = $this->seasonalIngredientService->getAllIngredients();
        $totalCount = count($allIngredients);
        
        $this->assertGreaterThan(0, $totalCount);
        
        // Vérifier que chaque saison a des ingrédients
        $seasons = ['printemps', 'ete', 'automne', 'hiver'];
        foreach ($seasons as $season) {
            $seasonIngredients = $this->seasonalIngredientService->getIngredientsForSeason($season);
            $this->assertGreaterThan(0, count($seasonIngredients), "Season {$season} should have ingredients");
        }
    }

    public function testGetIngredientsWithNutritionalInfo(): void
    {
        $ingredients = $this->seasonalIngredientService->getCurrentSeasonIngredients();
        
        // Vérifier que certains ingrédients ont des informations nutritionnelles
        $hasNutritionalInfo = false;
        foreach ($ingredients as $ingredient) {
            if (isset($ingredient['nutrition'])) {
                $hasNutritionalInfo = true;
                $this->assertArrayHasKey('calories', $ingredient['nutrition']);
                $this->assertArrayHasKey('vitamins', $ingredient['nutrition']);
                break;
            }
        }
        
        // Au moins un ingrédient devrait avoir des informations nutritionnelles
        $this->assertTrue($hasNutritionalInfo, "At least one ingredient should have nutritional information");
    }

    public function testGetIngredientsWithStorageInfo(): void
    {
        $ingredients = $this->seasonalIngredientService->getCurrentSeasonIngredients();
        
        // Vérifier que certains ingrédients ont des informations de stockage
        $hasStorageInfo = false;
        foreach ($ingredients as $ingredient) {
            if (isset($ingredient['storage'])) {
                $hasStorageInfo = true;
                $this->assertArrayHasKey('temperature', $ingredient['storage']);
                $this->assertArrayHasKey('duration', $ingredient['storage']);
                break;
            }
        }
        
        // Au moins un ingrédient devrait avoir des informations de stockage
        $this->assertTrue($hasStorageInfo, "At least one ingredient should have storage information");
    }
}
