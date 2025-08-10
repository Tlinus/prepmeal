<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\Unit\TestCase;
use App\Core\Services\UnitConversionService;

class UnitConversionServiceTest extends TestCase
{
    private UnitConversionService $unitConversionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unitConversionService = new UnitConversionService();
    }

    public function testConvertWeightFromMetricToImperial(): void
    {
        // Test grammes vers onces
        $result = $this->unitConversionService->convertQuantity(100, 'g', 'oz');
        $this->assertEqualsWithDelta(3.53, $result, 0.01);

        // Test kilogrammes vers livres
        $result = $this->unitConversionService->convertQuantity(1, 'kg', 'lb');
        $this->assertEqualsWithDelta(2.20, $result, 0.01);

        // Test grammes vers livres
        $result = $this->unitConversionService->convertQuantity(500, 'g', 'lb');
        $this->assertEqualsWithDelta(1.10, $result, 0.01);
    }

    public function testConvertWeightFromImperialToMetric(): void
    {
        // Test onces vers grammes
        $result = $this->unitConversionService->convertQuantity(8, 'oz', 'g');
        $this->assertEqualsWithDelta(226.80, $result, 0.01);

        // Test livres vers kilogrammes
        $result = $this->unitConversionService->convertQuantity(2, 'lb', 'kg');
        $this->assertEqualsWithDelta(0.91, $result, 0.01);

        // Test livres vers grammes
        $result = $this->unitConversionService->convertQuantity(1, 'lb', 'g');
        $this->assertEqualsWithDelta(453.59, $result, 0.01);
    }

    public function testConvertVolumeFromMetricToImperial(): void
    {
        // Test millilitres vers onces liquides
        $result = $this->unitConversionService->convertQuantity(250, 'ml', 'fl_oz');
        $this->assertEqualsWithDelta(8.45, $result, 0.01);

        // Test litres vers tasses
        $result = $this->unitConversionService->convertQuantity(1, 'l', 'cup');
        $this->assertEqualsWithDelta(4.23, $result, 0.01);

        // Test millilitres vers tasses
        $result = $this->unitConversionService->convertQuantity(500, 'ml', 'cup');
        $this->assertEqualsWithDelta(2.11, $result, 0.01);
    }

    public function testConvertVolumeFromImperialToMetric(): void
    {
        // Test onces liquides vers millilitres
        $result = $this->unitConversionService->convertQuantity(16, 'fl_oz', 'ml');
        $this->assertEqualsWithDelta(473.18, $result, 0.01);

        // Test tasses vers litres
        $result = $this->unitConversionService->convertQuantity(2, 'cup', 'l');
        $this->assertEqualsWithDelta(0.47, $result, 0.01);

        // Test tasses vers millilitres
        $result = $this->unitConversionService->convertQuantity(1, 'cup', 'ml');
        $this->assertEqualsWithDelta(236.59, $result, 0.01);
    }

    public function testConvertTemperatureFromCelsiusToFahrenheit(): void
    {
        // Test 0°C vers °F
        $result = $this->unitConversionService->convertQuantity(0, '°C', '°F');
        $this->assertEquals(32, $result);

        // Test 100°C vers °F
        $result = $this->unitConversionService->convertQuantity(100, '°C', '°F');
        $this->assertEquals(212, $result);

        // Test température ambiante (20°C)
        $result = $this->unitConversionService->convertQuantity(20, '°C', '°F');
        $this->assertEquals(68, $result);

        // Test température négative
        $result = $this->unitConversionService->convertQuantity(-10, '°C', '°F');
        $this->assertEquals(14, $result);
    }

    public function testConvertTemperatureFromFahrenheitToCelsius(): void
    {
        // Test 32°F vers °C
        $result = $this->unitConversionService->convertQuantity(32, '°F', '°C');
        $this->assertEquals(0, $result);

        // Test 212°F vers °C
        $result = $this->unitConversionService->convertQuantity(212, '°F', '°C');
        $this->assertEquals(100, $result);

        // Test température ambiante (68°F)
        $result = $this->unitConversionService->convertQuantity(68, '°F', '°C');
        $this->assertEquals(20, $result);

        // Test température négative
        $result = $this->unitConversionService->convertQuantity(14, '°F', '°C');
        $this->assertEquals(-10, $result);
    }

    public function testConvertUnitsWithSameUnit(): void
    {
        // Test conversion vers la même unité (doit retourner la valeur originale)
        $result = $this->unitConversionService->convertQuantity(100, 'g', 'g');
        $this->assertEquals(100, $result);

        $result = $this->unitConversionService->convertQuantity(250, 'ml', 'ml');
        $this->assertEquals(250, $result);

        $result = $this->unitConversionService->convertQuantity(20, '°C', '°C');
        $this->assertEquals(20, $result);
    }

    public function testConvertUnitsWithInvalidUnits(): void
    {
        // Test avec des unités invalides
        $this->expectException(\InvalidArgumentException::class);
        $this->unitConversionService->convertQuantity(100, 'invalid_unit', 'g');
    }

    public function testConvertUnitsWithUnsupportedConversion(): void
    {
        // Test conversion entre types d'unités non supportés
        $this->expectException(\InvalidArgumentException::class);
        $this->unitConversionService->convertQuantity(100, 'g', 'ml');
    }

    public function testGetSupportedUnits(): void
    {
        $weightUnits = $this->unitConversionService->getSupportedUnits('weight');
        $this->assertContains('g', $weightUnits);
        $this->assertContains('kg', $weightUnits);
        $this->assertContains('oz', $weightUnits);
        $this->assertContains('lb', $weightUnits);

        $volumeUnits = $this->unitConversionService->getSupportedUnits('volume');
        $this->assertContains('ml', $volumeUnits);
        $this->assertContains('l', $volumeUnits);
        $this->assertContains('fl_oz', $volumeUnits);
        $this->assertContains('cup', $volumeUnits);

        $temperatureUnits = $this->unitConversionService->getSupportedUnits('temperature');
        $this->assertContains('°C', $temperatureUnits);
        $this->assertContains('°F', $temperatureUnits);
    }

    public function testGetUnitType(): void
    {
        $this->assertEquals('weight', $this->unitConversionService->getUnitType('g'));
        $this->assertEquals('weight', $this->unitConversionService->getUnitType('kg'));
        $this->assertEquals('weight', $this->unitConversionService->getUnitType('oz'));
        $this->assertEquals('weight', $this->unitConversionService->getUnitType('lb'));

        $this->assertEquals('volume', $this->unitConversionService->getUnitType('ml'));
        $this->assertEquals('volume', $this->unitConversionService->getUnitType('l'));
        $this->assertEquals('volume', $this->unitConversionService->getUnitType('fl_oz'));
        $this->assertEquals('volume', $this->unitConversionService->getUnitType('cup'));

        $this->assertEquals('temperature', $this->unitConversionService->getUnitType('°C'));
        $this->assertEquals('temperature', $this->unitConversionService->getUnitType('°F'));
    }

    public function testGetUnitTypeWithInvalidUnit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->unitConversionService->getUnitType('invalid_unit');
    }

    public function testConvertRecipeIngredients(): void
    {
        $ingredients = [
            [
                'name' => ['fr' => 'farine'],
                'quantity' => [
                    'metric' => ['amount' => 200, 'unit' => 'g'],
                    'imperial' => ['amount' => 7, 'unit' => 'oz']
                ]
            ],
            [
                'name' => ['fr' => 'lait'],
                'quantity' => [
                    'metric' => ['amount' => 250, 'unit' => 'ml'],
                    'imperial' => ['amount' => 8.5, 'unit' => 'fl_oz']
                ]
            ]
        ];

        $convertedIngredients = $this->unitConversionService->convertRecipeIngredients($ingredients, 'imperial');

        $this->assertCount(2, $convertedIngredients);
        
        // Vérifier la conversion de la farine
        $farine = $convertedIngredients[0];
        $this->assertEquals('farine', $farine['name']['fr']);
        $this->assertEquals(7, $farine['quantity']['imperial']['amount']);
        $this->assertEquals('oz', $farine['quantity']['imperial']['unit']);

        // Vérifier la conversion du lait
        $lait = $convertedIngredients[1];
        $this->assertEquals('lait', $lait['name']['fr']);
        $this->assertEquals(8.5, $lait['quantity']['imperial']['amount']);
        $this->assertEquals('fl_oz', $lait['quantity']['imperial']['unit']);
    }

    public function testConvertRecipeIngredientsToMetric(): void
    {
        $ingredients = [
            [
                'name' => ['fr' => 'farine'],
                'quantity' => [
                    'metric' => ['amount' => 200, 'unit' => 'g'],
                    'imperial' => ['amount' => 7, 'unit' => 'oz']
                ]
            ]
        ];

        $convertedIngredients = $this->unitConversionService->convertRecipeIngredients($ingredients, 'metric');

        $this->assertCount(1, $convertedIngredients);
        
        $farine = $convertedIngredients[0];
        $this->assertEquals('farine', $farine['name']['fr']);
        $this->assertEquals(200, $farine['quantity']['metric']['amount']);
        $this->assertEquals('g', $farine['quantity']['metric']['unit']);
    }

    public function testConvertRecipeIngredientsWithInvalidSystem(): void
    {
        $ingredients = [
            [
                'name' => ['fr' => 'farine'],
                'quantity' => [
                    'metric' => ['amount' => 200, 'unit' => 'g'],
                    'imperial' => ['amount' => 7, 'unit' => 'oz']
                ]
            ]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->unitConversionService->convertRecipeIngredients($ingredients, 'invalid_system');
    }
}
