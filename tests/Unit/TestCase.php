<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    protected ContainerInterface $container;
    protected LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer un logger de test (NullHandler pour ne pas écrire de logs)
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new NullHandler());
        
        // Créer un container de test minimal
        $this->container = $this->createTestContainer();
    }

    protected function createTestContainer(): ContainerInterface
    {
        return new class($this->logger) implements ContainerInterface {
            private LoggerInterface $logger;

            public function __construct(LoggerInterface $logger)
            {
                $this->logger = $logger;
            }

            public function get(string $id)
            {
                if ($id === LoggerInterface::class) {
                    return $this->logger;
                }
                
                throw new \Exception("Service not found: {$id}");
            }

            public function has(string $id): bool
            {
                return $id === LoggerInterface::class;
            }
        };
    }

    protected function createMockRecipe(array $overrides = []): array
    {
        $defaultRecipe = [
            'id' => 'recipe_001',
            'title' => [
                'fr' => 'Salade de quinoa aux légumes de saison',
                'en' => 'Seasonal vegetable quinoa salad'
            ],
            'description' => [
                'fr' => 'Une salade nutritive et colorée parfaite pour l\'été',
                'en' => 'A nutritious and colorful salad perfect for summer'
            ],
            'category' => 'equilibre',
            'season' => ['printemps', 'ete'],
            'prep_time' => 15,
            'cook_time' => 20,
            'servings' => 4,
            'difficulty' => 'facile',
            'nutrition' => [
                'calories' => 320,
                'protein' => 12,
                'carbs' => 45,
                'fat' => 8,
                'fiber' => 6
            ],
            'allergens' => ['gluten'],
            'dietary_restrictions' => ['vegetarien', 'vegan'],
            'ingredients' => [
                [
                    'name' => [
                        'fr' => 'quinoa',
                        'en' => 'quinoa'
                    ],
                    'quantity' => [
                        'metric' => ['amount' => 200, 'unit' => 'g'],
                        'imperial' => ['amount' => 7, 'unit' => 'oz']
                    ],
                    'seasonal' => false
                ],
                [
                    'name' => [
                        'fr' => 'courgettes',
                        'en' => 'zucchini'
                    ],
                    'quantity' => [
                        'metric' => ['amount' => 2, 'unit' => 'pièces'],
                        'imperial' => ['amount' => 2, 'unit' => 'pieces']
                    ],
                    'seasonal' => true,
                    'season' => ['ete', 'automne']
                ]
            ],
            'instructions' => [
                [
                    'step' => 1,
                    'text' => [
                        'fr' => 'Rincer le quinoa et le faire cuire dans 400ml d\'eau salée pendant 15 minutes',
                        'en' => 'Rinse quinoa and cook in 400ml salted water for 15 minutes'
                    ]
                ]
            ],
            'tags' => ['leger', 'rapide', 'sain'],
            'image_url' => '/images/recipes/recipe_001.jpg'
        ];

        return array_merge($defaultRecipe, $overrides);
    }

    protected function createMockUser(array $overrides = []): array
    {
        $defaultUser = [
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'locale' => 'fr',
            'units' => 'metric',
            'subscription_status' => 'active',
            'subscription_plan' => 'monthly'
        ];

        return array_merge($defaultUser, $overrides);
    }

    protected function assertArrayHasKeys(array $expectedKeys, array $array, string $message = ''): void
    {
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should contain key '{$key}'");
        }
    }

    protected function assertNutritionalValues(array $expected, array $actual, float $tolerance = 0.01): void
    {
        $this->assertArrayHasKeys(['calories', 'protein', 'carbs', 'fat'], $actual);
        
        foreach ($expected as $nutrient => $value) {
            $this->assertEqualsWithDelta(
                $value, 
                $actual[$nutrient], 
                $tolerance, 
                "Nutritional value for {$nutrient} should match"
            );
        }
    }
}
