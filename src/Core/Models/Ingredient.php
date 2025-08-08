<?php

declare(strict_types=1);

namespace PrepMeal\Core\Models;

class Ingredient
{
    public function __construct(
        private array $name,
        private array $quantity,
        private bool $seasonal = false,
        private array $season = []
    ) {}

    public function getName(string $locale = 'fr'): string
    {
        return $this->name[$locale] ?? $this->name['fr'] ?? '';
    }

    public function getNames(): array
    {
        return $this->name;
    }

    public function getQuantity(string $system = 'metric'): array
    {
        return $this->quantity[$system] ?? $this->quantity['metric'] ?? [];
    }

    public function getQuantities(): array
    {
        return $this->quantity;
    }

    public function getAmount(string $system = 'metric'): float
    {
        $quantity = $this->getQuantity($system);
        return $quantity['amount'] ?? 0.0;
    }

    public function getUnit(string $system = 'metric'): string
    {
        $quantity = $this->getQuantity($system);
        return $quantity['unit'] ?? '';
    }

    public function isSeasonal(): bool
    {
        return $this->seasonal;
    }

    public function getSeason(): array
    {
        return $this->season;
    }

    public function isInSeason(string $season): bool
    {
        return in_array($season, $this->season);
    }

    public function getFormattedQuantity(string $locale = 'fr', string $system = 'metric'): string
    {
        $amount = $this->getAmount($system);
        $unit = $this->getUnit($system);
        
        // Formatage selon la locale
        switch ($locale) {
            case 'fr':
                return number_format($amount, 1, ',', ' ') . ' ' . $unit;
            case 'en':
                return number_format($amount, 1, '.', ',') . ' ' . $unit;
            case 'es':
                return number_format($amount, 1, ',', '.') . ' ' . $unit;
            case 'de':
                return number_format($amount, 1, ',', '.') . ' ' . $unit;
            default:
                return $amount . ' ' . $unit;
        }
    }

    public function convertToImperial(): array
    {
        $metricQuantity = $this->getQuantity('metric');
        $amount = $metricQuantity['amount'] ?? 0;
        $unit = $metricQuantity['unit'] ?? '';

        // Conversions de base (à étendre selon les besoins)
        $conversions = [
            'g' => ['unit' => 'oz', 'factor' => 0.035274],
            'kg' => ['unit' => 'lb', 'factor' => 2.20462],
            'ml' => ['unit' => 'fl oz', 'factor' => 0.033814],
            'l' => ['unit' => 'cups', 'factor' => 4.22675],
            'cm' => ['unit' => 'in', 'factor' => 0.393701],
            'm' => ['unit' => 'ft', 'factor' => 3.28084],
        ];

        if (isset($conversions[$unit])) {
            $conversion = $conversions[$unit];
            return [
                'amount' => round($amount * $conversion['factor'], 2),
                'unit' => $conversion['unit']
            ];
        }

        // Si pas de conversion disponible, retourner les valeurs métriques
        return $metricQuantity;
    }

    public function convertToMetric(): array
    {
        $imperialQuantity = $this->getQuantity('imperial');
        $amount = $imperialQuantity['amount'] ?? 0;
        $unit = $imperialQuantity['unit'] ?? '';

        // Conversions inverses
        $conversions = [
            'oz' => ['unit' => 'g', 'factor' => 28.3495],
            'lb' => ['unit' => 'kg', 'factor' => 0.453592],
            'fl oz' => ['unit' => 'ml', 'factor' => 29.5735],
            'cups' => ['unit' => 'l', 'factor' => 0.236588],
            'in' => ['unit' => 'cm', 'factor' => 2.54],
            'ft' => ['unit' => 'm', 'factor' => 0.3048],
        ];

        if (isset($conversions[$unit])) {
            $conversion = $conversions[$unit];
            return [
                'amount' => round($amount * $conversion['factor'], 2),
                'unit' => $conversion['unit']
            ];
        }

        // Si pas de conversion disponible, retourner les valeurs impériales
        return $imperialQuantity;
    }

    public function toArray(string $locale = 'fr'): array
    {
        return [
            'name' => $this->getName($locale),
            'names' => $this->getNames(),
            'quantity' => $this->getQuantities(),
            'formatted_quantity' => $this->getFormattedQuantity($locale),
            'seasonal' => $this->seasonal,
            'season' => $this->season,
            'is_seasonal' => $this->isSeasonal(),
        ];
    }

    public function toJson(string $locale = 'fr'): string
    {
        return json_encode($this->toArray($locale), JSON_UNESCAPED_UNICODE);
    }
}
