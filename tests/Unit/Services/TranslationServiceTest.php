<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\Unit\TestCase;
use App\Core\Services\TranslationService;

class TranslationServiceTest extends TestCase
{
    private TranslationService $translationService;
    private string $testLocalesPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer un dossier temporaire pour les tests
        $this->testLocalesPath = sys_get_temp_dir() . '/prepmeal_test_locales';
        if (!is_dir($this->testLocalesPath)) {
            mkdir($this->testLocalesPath, 0777, true);
        }
        
        $this->createTestTranslationFiles();
        $this->translationService = new TranslationService($this->testLocalesPath);
    }

    protected function tearDown(): void
    {
        // Nettoyer les fichiers de test
        if (is_dir($this->testLocalesPath)) {
            $this->removeDirectory($this->testLocalesPath);
        }
        parent::tearDown();
    }

    private function createTestTranslationFiles(): void
    {
        // Fichier français
        $frTranslations = [
            'common' => [
                'save' => 'Sauvegarder',
                'cancel' => 'Annuler',
                'delete' => 'Supprimer',
                'edit' => 'Modifier'
            ],
            'diet_types' => [
                'equilibre' => 'Équilibré',
                'vegan' => 'Végétalien',
                'vegetarien' => 'Végétarien'
            ],
            'allergens' => [
                'gluten' => 'Gluten',
                'lactose' => 'Lactose'
            ],
            'seasons' => [
                'printemps' => 'Printemps',
                'ete' => 'Été'
            ],
            'units' => [
                'g' => 'grammes',
                'kg' => 'kilogrammes'
            ],
            'meal_types' => [
                'breakfast' => 'Petit-déjeuner',
                'lunch' => 'Déjeuner',
                'dinner' => 'Dîner'
            ],
            'difficulties' => [
                'facile' => 'Facile',
                'moyen' => 'Moyen',
                'difficile' => 'Difficile'
            ],
            'categories' => [
                'entree' => 'Entrée',
                'plat' => 'Plat principal',
                'dessert' => 'Dessert'
            ]
        ];

        file_put_contents(
            $this->testLocalesPath . '/fr.json',
            json_encode($frTranslations, JSON_UNESCAPED_UNICODE)
        );

        // Fichier anglais
        $enTranslations = [
            'common' => [
                'save' => 'Save',
                'cancel' => 'Cancel',
                'delete' => 'Delete',
                'edit' => 'Edit'
            ],
            'diet_types' => [
                'equilibre' => 'Balanced',
                'vegan' => 'Vegan',
                'vegetarien' => 'Vegetarian'
            ],
            'allergens' => [
                'gluten' => 'Gluten',
                'lactose' => 'Lactose'
            ],
            'seasons' => [
                'printemps' => 'Spring',
                'ete' => 'Summer'
            ],
            'units' => [
                'g' => 'grams',
                'kg' => 'kilograms'
            ],
            'meal_types' => [
                'breakfast' => 'Breakfast',
                'lunch' => 'Lunch',
                'dinner' => 'Dinner'
            ],
            'difficulties' => [
                'facile' => 'Easy',
                'moyen' => 'Medium',
                'difficile' => 'Hard'
            ],
            'categories' => [
                'entree' => 'Appetizer',
                'plat' => 'Main course',
                'dessert' => 'Dessert'
            ]
        ];

        file_put_contents(
            $this->testLocalesPath . '/en.json',
            json_encode($enTranslations, JSON_UNESCAPED_UNICODE)
        );

        // Fichier espagnol (incomplet pour tester le fallback)
        $esTranslations = [
            'common' => [
                'save' => 'Guardar',
                'cancel' => 'Cancelar'
            ],
            'diet_types' => [
                'equilibre' => 'Equilibrado'
            ]
        ];

        file_put_contents(
            $this->testLocalesPath . '/es.json',
            json_encode($esTranslations, JSON_UNESCAPED_UNICODE)
        );
    }

    private function removeDirectory(string $path): void
    {
        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $path . '/' . $file;
                    if (is_dir($filePath)) {
                        $this->removeDirectory($filePath);
                    } else {
                        unlink($filePath);
                    }
                }
            }
            rmdir($path);
        }
    }

    public function testTranslateWithValidKey(): void
    {
        $result = $this->translationService->translate('common.save', 'fr');
        $this->assertEquals('Sauvegarder', $result);

        $result = $this->translationService->translate('common.save', 'en');
        $this->assertEquals('Save', $result);
    }

    public function testTranslateWithNestedKey(): void
    {
        $result = $this->translationService->translate('diet_types.vegan', 'fr');
        $this->assertEquals('Végétalien', $result);

        $result = $this->translationService->translate('diet_types.vegan', 'en');
        $this->assertEquals('Vegan', $result);
    }

    public function testTranslateWithFallbackToDefault(): void
    {
        // Test avec une clé qui n'existe qu'en français (locale par défaut)
        $result = $this->translationService->translate('common.edit', 'en');
        $this->assertEquals('Modifier', $result); // Fallback vers le français
    }

    public function testTranslateWithMissingKey(): void
    {
        // Test avec une clé qui n'existe dans aucune langue
        $result = $this->translationService->translate('nonexistent.key', 'fr');
        $this->assertEquals('nonexistent.key', $result); // Retourne la clé si pas trouvée
    }

    public function testTranslateWithInvalidLocale(): void
    {
        // Test avec une locale invalide
        $result = $this->translationService->translate('common.save', 'invalid_locale');
        $this->assertEquals('Sauvegarder', $result); // Fallback vers le français par défaut
    }

    public function testGetDietTypes(): void
    {
        $dietTypes = $this->translationService->getDietTypes('fr');
        $this->assertArrayHasKey('equilibre', $dietTypes);
        $this->assertArrayHasKey('vegan', $dietTypes);
        $this->assertArrayHasKey('vegetarien', $dietTypes);
        $this->assertEquals('Équilibré', $dietTypes['equilibre']);

        $dietTypes = $this->translationService->getDietTypes('en');
        $this->assertEquals('Balanced', $dietTypes['equilibre']);
        $this->assertEquals('Vegan', $dietTypes['vegan']);
    }

    public function testGetAllergens(): void
    {
        $allergens = $this->translationService->getAllergens('fr');
        $this->assertArrayHasKey('gluten', $allergens);
        $this->assertArrayHasKey('lactose', $allergens);
        $this->assertEquals('Gluten', $allergens['gluten']);

        $allergens = $this->translationService->getAllergens('en');
        $this->assertEquals('Gluten', $allergens['gluten']);
        $this->assertEquals('Lactose', $allergens['lactose']);
    }

    public function testGetSeasons(): void
    {
        $seasons = $this->translationService->getSeasons('fr');
        $this->assertArrayHasKey('printemps', $seasons);
        $this->assertArrayHasKey('ete', $seasons);
        $this->assertEquals('Printemps', $seasons['printemps']);

        $seasons = $this->translationService->getSeasons('en');
        $this->assertEquals('Spring', $seasons['printemps']);
        $this->assertEquals('Summer', $seasons['ete']);
    }

    public function testGetUnits(): void
    {
        $units = $this->translationService->getUnits('fr');
        $this->assertArrayHasKey('g', $units);
        $this->assertArrayHasKey('kg', $units);
        $this->assertEquals('grammes', $units['g']);

        $units = $this->translationService->getUnits('en');
        $this->assertEquals('grams', $units['g']);
        $this->assertEquals('kilograms', $units['kg']);
    }

    public function testGetMealTypes(): void
    {
        $mealTypes = $this->translationService->getMealTypes('fr');
        $this->assertArrayHasKey('breakfast', $mealTypes);
        $this->assertArrayHasKey('lunch', $mealTypes);
        $this->assertArrayHasKey('dinner', $mealTypes);
        $this->assertEquals('Petit-déjeuner', $mealTypes['breakfast']);

        $mealTypes = $this->translationService->getMealTypes('en');
        $this->assertEquals('Breakfast', $mealTypes['breakfast']);
        $this->assertEquals('Lunch', $mealTypes['lunch']);
    }

    public function testGetDifficulties(): void
    {
        $difficulties = $this->translationService->getDifficulties('fr');
        $this->assertArrayHasKey('facile', $difficulties);
        $this->assertArrayHasKey('moyen', $difficulties);
        $this->assertArrayHasKey('difficile', $difficulties);
        $this->assertEquals('Facile', $difficulties['facile']);

        $difficulties = $this->translationService->getDifficulties('en');
        $this->assertEquals('Easy', $difficulties['facile']);
        $this->assertEquals('Medium', $difficulties['moyen']);
    }

    public function testGetCategories(): void
    {
        $categories = $this->translationService->getCategories('fr');
        $this->assertArrayHasKey('entree', $categories);
        $this->assertArrayHasKey('plat', $categories);
        $this->assertArrayHasKey('dessert', $categories);
        $this->assertEquals('Entrée', $categories['entree']);

        $categories = $this->translationService->getCategories('en');
        $this->assertEquals('Appetizer', $categories['entree']);
        $this->assertEquals('Main course', $categories['plat']);
    }

    public function testGetSupportedLocales(): void
    {
        $locales = $this->translationService->getSupportedLocales();
        $this->assertContains('fr', $locales);
        $this->assertContains('en', $locales);
        $this->assertContains('es', $locales);
    }

    public function testIsLocaleSupported(): void
    {
        $this->assertTrue($this->translationService->isLocaleSupported('fr'));
        $this->assertTrue($this->translationService->isLocaleSupported('en'));
        $this->assertTrue($this->translationService->isLocaleSupported('es'));
        $this->assertFalse($this->translationService->isLocaleSupported('de'));
        $this->assertFalse($this->translationService->isLocaleSupported('invalid'));
    }

    public function testGetDefaultLocale(): void
    {
        $defaultLocale = $this->translationService->getDefaultLocale();
        $this->assertEquals('fr', $defaultLocale);
    }

    public function testTranslateWithFallbackChain(): void
    {
        // Test avec une clé qui n'existe qu'en espagnol, puis en français
        $result = $this->translationService->translate('common.save', 'es');
        $this->assertEquals('Guardar', $result); // Existe en espagnol

        // Test avec une clé qui n'existe qu'en français
        $result = $this->translationService->translate('common.edit', 'es');
        $this->assertEquals('Modifier', $result); // Fallback vers le français
    }

    public function testTranslateWithEmptyTranslationFile(): void
    {
        // Créer un fichier de traduction vide
        file_put_contents($this->testLocalesPath . '/empty.json', '{}');
        
        $translationService = new TranslationService($this->testLocalesPath);
        $result = $translationService->translate('common.save', 'empty');
        $this->assertEquals('Sauvegarder', $result); // Fallback vers le français
    }

    public function testTranslateWithMalformedTranslationFile(): void
    {
        // Créer un fichier de traduction malformé
        file_put_contents($this->testLocalesPath . '/malformed.json', '{ invalid json }');
        
        $translationService = new TranslationService($this->testLocalesPath);
        $result = $translationService->translate('common.save', 'malformed');
        $this->assertEquals('Sauvegarder', $result); // Fallback vers le français
    }
}
