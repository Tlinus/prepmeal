<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

class UnitConversionService
{
    private array $conversionFactors = [
        'weight' => [
            'g' => ['oz' => 0.035274, 'lb' => 0.00220462],
            'kg' => ['oz' => 35.274, 'lb' => 2.20462],
            'oz' => ['g' => 28.3495, 'kg' => 0.0283495],
            'lb' => ['g' => 453.592, 'kg' => 0.453592]
        ],
        'volume' => [
            'ml' => ['fl_oz' => 0.033814, 'cup' => 0.00422675],
            'l' => ['fl_oz' => 33.814, 'cup' => 4.22675],
            'fl_oz' => ['ml' => 29.5735, 'l' => 0.0295735],
            'cup' => ['ml' => 236.588, 'l' => 0.236588]
        ],
        'temperature' => [
            'celsius' => ['fahrenheit' => 'celsius_to_fahrenheit'],
            'fahrenheit' => ['celsius' => 'fahrenheit_to_celsius']
        ]
    ];

    private array $unitMappings = [
        'metric' => [
            'weight' => ['g', 'kg'],
            'volume' => ['ml', 'l'],
            'temperature' => ['celsius'],
            'pieces' => ['pièces', 'pieces']
        ],
        'imperial' => [
            'weight' => ['oz', 'lb'],
            'volume' => ['fl_oz', 'cup'],
            'temperature' => ['fahrenheit'],
            'pieces' => ['pieces', 'pièces']
        ]
    ];

    public function convertQuantity(array $quantity, string $targetSystem): array
    {
        // Si la quantité a déjà la structure attendue (amount + unit)
        if (isset($quantity['amount']) && isset($quantity['unit'])) {
            return $quantity;
        }

        // Si la quantité a la structure métrique/impériale
        if (isset($quantity['metric']) && isset($quantity['imperial'])) {
            if ($targetSystem === 'metric') {
                return $quantity['metric'];
            } elseif ($targetSystem === 'imperial') {
                return $quantity['imperial'];
            }
            return $quantity['metric']; // Par défaut, retourner métrique
        }

        // Si la structure est invalide, retourner une quantité par défaut
        return ['amount' => 0, 'unit' => ''];
    }

    public function addQuantities(array $quantity1, array $quantity2): array
    {
        $result = [];
        
        // Additionner les quantités simples (amount + unit)
        if (isset($quantity1['amount']) && isset($quantity2['amount'])) {
            $result['amount'] = $quantity1['amount'] + $quantity2['amount'];
            $result['unit'] = $quantity1['unit'] ?? $quantity2['unit'];
        } elseif (isset($quantity1['metric']) && isset($quantity2['metric'])) {
            // Additionner les quantités métriques
            $result['metric'] = [
                'amount' => $quantity1['metric']['amount'] + $quantity2['metric']['amount'],
                'unit' => $quantity1['metric']['unit'] ?? $quantity2['metric']['unit']
            ];
            // Additionner les quantités impériales
            if (isset($quantity1['imperial']) && isset($quantity2['imperial'])) {
                $result['imperial'] = [
                    'amount' => $quantity1['imperial']['amount'] + $quantity2['imperial']['amount'],
                    'unit' => $quantity1['imperial']['unit'] ?? $quantity2['imperial']['unit']
                ];
            }
        } else {
            // Si les structures ne correspondent pas, essayer de les normaliser
            $q1 = $this->normalizeQuantity($quantity1);
            $q2 = $this->normalizeQuantity($quantity2);
            
            $result['amount'] = $q1['amount'] + $q2['amount'];
            $result['unit'] = $q1['unit'] ?? $q2['unit'];
        }
        
        return $result;
    }
    
    private function normalizeQuantity(array $quantity): array
    {
        // Si c'est déjà une quantité simple
        if (isset($quantity['amount']) && isset($quantity['unit'])) {
            return $quantity;
        }
        
        // Si c'est une quantité métrique/impériale, prendre la métrique par défaut
        if (isset($quantity['metric'])) {
            return $quantity['metric'];
        }
        
        // Sinon, retourner une quantité par défaut
        return ['amount' => 0, 'unit' => ''];
    }

    public function convertWeight(float $amount, string $fromUnit, string $toUnit): float
    {
        if ($fromUnit === $toUnit) {
            return $amount;
        }

        $factors = $this->conversionFactors['weight'][$fromUnit] ?? null;
        if (!$factors || !isset($factors[$toUnit])) {
            throw new \InvalidArgumentException("Conversion non supportée de {$fromUnit} vers {$toUnit}");
        }

        return $amount * $factors[$toUnit];
    }

    public function convertVolume(float $amount, string $fromUnit, string $toUnit): float
    {
        if ($fromUnit === $toUnit) {
            return $amount;
        }

        $factors = $this->conversionFactors['volume'][$fromUnit] ?? null;
        if (!$factors || !isset($factors[$toUnit])) {
            throw new \InvalidArgumentException("Conversion non supportée de {$fromUnit} vers {$toUnit}");
        }

        return $amount * $factors[$toUnit];
    }

    public function convertTemperature(float $amount, string $fromUnit, string $toUnit): float
    {
        if ($fromUnit === $toUnit) {
            return $amount;
        }

        if ($fromUnit === 'celsius' && $toUnit === 'fahrenheit') {
            return ($amount * 9/5) + 32;
        } elseif ($fromUnit === 'fahrenheit' && $toUnit === 'celsius') {
            return ($amount - 32) * 5/9;
        }

        throw new \InvalidArgumentException("Conversion de température non supportée de {$fromUnit} vers {$toUnit}");
    }

    public function formatQuantity(array $quantity, string $system, string $locale = 'fr'): string
    {
        $amount = $quantity['amount'] ?? 0;
        $unit = $quantity['unit'] ?? '';

        // Formater selon la locale
        $formattedAmount = $this->formatNumber($amount, $locale);
        
        // Traduire l'unité si nécessaire
        $translatedUnit = $this->translateUnit($unit, $locale);

        return "{$formattedAmount} {$translatedUnit}";
    }

    private function formatNumber(float $number, string $locale): string
    {
        switch ($locale) {
            case 'fr':
                return number_format($number, 1, ',', ' ');
            case 'de':
                return number_format($number, 1, ',', '.');
            case 'es':
                return number_format($number, 1, ',', '.');
            default:
                return number_format($number, 1, '.', ',');
        }
    }

    private function translateUnit(string $unit, string $locale): string
    {
        $translations = [
            'g' => ['fr' => 'g', 'en' => 'g', 'es' => 'g', 'de' => 'g'],
            'kg' => ['fr' => 'kg', 'en' => 'kg', 'es' => 'kg', 'de' => 'kg'],
            'ml' => ['fr' => 'ml', 'en' => 'ml', 'es' => 'ml', 'de' => 'ml'],
            'l' => ['fr' => 'l', 'en' => 'l', 'es' => 'l', 'de' => 'l'],
            'oz' => ['fr' => 'oz', 'en' => 'oz', 'es' => 'oz', 'de' => 'oz'],
            'lb' => ['fr' => 'lb', 'en' => 'lb', 'es' => 'lb', 'de' => 'lb'],
            'fl_oz' => ['fr' => 'fl oz', 'en' => 'fl oz', 'es' => 'fl oz', 'de' => 'fl oz'],
            'cup' => ['fr' => 'tasse', 'en' => 'cup', 'es' => 'taza', 'de' => 'Tasse'],
            'pieces' => ['fr' => 'pièces', 'en' => 'pieces', 'es' => 'piezas', 'de' => 'Stücke'],
            'pièces' => ['fr' => 'pièces', 'en' => 'pieces', 'es' => 'piezas', 'de' => 'Stücke']
        ];

        return $translations[$unit][$locale] ?? $unit;
    }

    public function getSystemUnits(string $system): array
    {
        return $this->unitMappings[$system] ?? $this->unitMappings['metric'];
    }

    public function isMetricUnit(string $unit): bool
    {
        return in_array($unit, $this->unitMappings['metric']['weight']) ||
               in_array($unit, $this->unitMappings['metric']['volume']) ||
               in_array($unit, $this->unitMappings['metric']['temperature']);
    }

    public function isImperialUnit(string $unit): bool
    {
        return in_array($unit, $this->unitMappings['imperial']['weight']) ||
               in_array($unit, $this->unitMappings['imperial']['volume']) ||
               in_array($unit, $this->unitMappings['imperial']['temperature']);
    }

    public function convertRecipeIngredients(array $ingredients, string $targetSystem): array
    {
        $convertedIngredients = [];

        foreach ($ingredients as $ingredient) {
            $convertedIngredient = $ingredient;
            
            if (isset($ingredient['quantity'])) {
                $convertedIngredient['quantity'] = $this->convertQuantity($ingredient['quantity'], $targetSystem);
            }

            $convertedIngredients[] = $convertedIngredient;
        }

        return $convertedIngredients;
    }

    public function convertNutritionalValues(array $nutrition, string $targetSystem): array
    {
        $convertedNutrition = $nutrition;

        // Convertir les calories si nécessaire (1 kcal = 4.184 kJ)
        if (isset($nutrition['calories']) && $targetSystem === 'imperial') {
            $convertedNutrition['calories'] = round($nutrition['calories'] * 4.184, 1);
        }

        return $convertedNutrition;
    }
}

