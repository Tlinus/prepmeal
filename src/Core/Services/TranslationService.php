<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

class TranslationService
{
    private array $translations = [];
    private string $defaultLocale = 'fr';
    private array $supportedLocales = ['fr', 'en', 'es', 'de'];

    public function __construct()
    {
        $this->loadTranslations();
    }

    private function loadTranslations(): void
    {
        foreach ($this->supportedLocales as $locale) {
            $file = __DIR__ . "/../../locales/{$locale}.json";
            if (file_exists($file)) {
                $this->translations[$locale] = json_decode(file_get_contents($file), true) ?? [];
            }
        }
    }

    public function translate(string $key, string $locale = 'fr', array $parameters = []): string
    {
        $translation = $this->getTranslation($key, $locale);
        
        if ($translation === null) {
            // Fallback vers la locale par défaut
            $translation = $this->getTranslation($key, $this->defaultLocale) ?? $key;
        }

        // Remplacer les paramètres
        foreach ($parameters as $param => $value) {
            $translation = str_replace(":{$param}", $value, $translation);
        }

        return $translation;
    }

    private function getTranslation(string $key, string $locale): ?string
    {
        $keys = explode('.', $key);
        $translation = $this->translations[$locale] ?? [];

        foreach ($keys as $k) {
            if (!isset($translation[$k])) {
                return null;
            }
            $translation = $translation[$k];
        }

        return is_string($translation) ? $translation : null;
    }

    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    public function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales);
    }

    public function getDietTypes(string $locale = 'fr'): array
    {
        return [
            'prise_masse' => $this->translate('diet_types.prise_masse', $locale),
            'equilibre' => $this->translate('diet_types.equilibre', $locale),
            'seche' => $this->translate('diet_types.seche', $locale),
            'anti_cholesterol' => $this->translate('diet_types.anti_cholesterol', $locale),
            'vegan' => $this->translate('diet_types.vegan', $locale),
            'vegetarien' => $this->translate('diet_types.vegetarien', $locale),
            'recettes_simples' => $this->translate('diet_types.recettes_simples', $locale),
            'cetogene' => $this->translate('diet_types.cetogene', $locale),
            'paleo' => $this->translate('diet_types.paleo', $locale),
            'sans_gluten' => $this->translate('diet_types.sans_gluten', $locale),
            'mediterraneen' => $this->translate('diet_types.mediterraneen', $locale)
        ];
    }

    public function getAllergens(string $locale = 'fr'): array
    {
        return [
            'gluten' => $this->translate('allergens.gluten', $locale),
            'lactose' => $this->translate('allergens.lactose', $locale),
            'fruits_coque' => $this->translate('allergens.fruits_coque', $locale),
            'oeufs' => $this->translate('allergens.oeufs', $locale),
            'soja' => $this->translate('allergens.soja', $locale),
            'poisson' => $this->translate('allergens.poisson', $locale),
            'crustaces' => $this->translate('allergens.crustaces', $locale),
            'arachides' => $this->translate('allergens.arachides', $locale),
            'moutarde' => $this->translate('allergens.moutarde', $locale),
            'celeri' => $this->translate('allergens.celeri', $locale),
            'sulfites' => $this->translate('allergens.sulfites', $locale),
            'lupin' => $this->translate('allergens.lupin', $locale),
            'mollusques' => $this->translate('allergens.mollusques', $locale)
        ];
    }

    public function getSeasons(string $locale = 'fr'): array
    {
        return [
            'printemps' => $this->translate('seasons.printemps', $locale),
            'ete' => $this->translate('seasons.ete', $locale),
            'automne' => $this->translate('seasons.automne', $locale),
            'hiver' => $this->translate('seasons.hiver', $locale)
        ];
    }

    public function getTranslations(string $locale = 'fr', array $sections = []): array
    {
        $translations = $this->translations[$locale] ?? $this->translations[$this->defaultLocale] ?? [];
        
        if (empty($sections)) {
            return $translations;
        }
        
        $result = [];
        foreach ($sections as $section) {
            if (isset($translations[$section])) {
                $result[$section] = $translations[$section];
            }
        }
        
        return $result;
    }

    public function getUnits(string $locale = 'fr'): array
    {
        return [
            'metric' => [
                'g' => $this->translate('units.metric.g', $locale),
                'kg' => $this->translate('units.metric.kg', $locale),
                'ml' => $this->translate('units.metric.ml', $locale),
                'l' => $this->translate('units.metric.l', $locale),
                'pieces' => $this->translate('units.metric.pieces', $locale),
                'cups' => $this->translate('units.metric.cups', $locale)
            ],
            'imperial' => [
                'oz' => $this->translate('units.imperial.oz', $locale),
                'lb' => $this->translate('units.imperial.lb', $locale),
                'fl_oz' => $this->translate('units.imperial.fl_oz', $locale),
                'cups' => $this->translate('units.imperial.cups', $locale),
                'pieces' => $this->translate('units.imperial.pieces', $locale),
                'tbsp' => $this->translate('units.imperial.tbsp', $locale),
                'tsp' => $this->translate('units.imperial.tsp', $locale)
            ]
        ];
    }

    public function getMealTypes(string $locale = 'fr'): array
    {
        return [
            'breakfast' => $this->translate('meal_types.breakfast', $locale),
            'lunch' => $this->translate('meal_types.lunch', $locale),
            'dinner' => $this->translate('meal_types.dinner', $locale),
            'snack' => $this->translate('meal_types.snack', $locale)
        ];
    }

    public function getDifficulties(string $locale = 'fr'): array
    {
        return [
            'facile' => $this->translate('difficulties.facile', $locale),
            'moyen' => $this->translate('difficulties.moyen', $locale),
            'difficile' => $this->translate('difficulties.difficile', $locale)
        ];
    }

    public function getCategories(string $locale = 'fr'): array
    {
        return [
            'petit_dejeuner' => $this->translate('categories.petit_dejeuner', $locale),
            'entree' => $this->translate('categories.entree', $locale),
            'plat_principal' => $this->translate('categories.plat_principal', $locale),
            'dessert' => $this->translate('categories.dessert', $locale),
            'equilibre' => $this->translate('categories.equilibre', $locale),
            'leger' => $this->translate('categories.leger', $locale),
            'vegetarien' => $this->translate('categories.vegetarien', $locale),
            'vegan' => $this->translate('categories.vegan', $locale)
        ];
    }

    public function formatNumber(float $number, string $locale = 'fr'): string
    {
        switch ($locale) {
            case 'fr':
                return number_format($number, 1, ',', ' ');
            case 'en':
                return number_format($number, 1, '.', ',');
            case 'es':
                return number_format($number, 1, ',', '.');
            case 'de':
                return number_format($number, 1, ',', '.');
            default:
                return number_format($number, 1);
        }
    }

    public function formatDate(\DateTime $date, string $locale = 'fr'): string
    {
        $formats = [
            'fr' => 'd/m/Y',
            'en' => 'm/d/Y',
            'es' => 'd/m/Y',
            'de' => 'd.m.Y'
        ];

        $format = $formats[$locale] ?? $formats['fr'];
        return $date->format($format);
    }

    public function formatTime(int $minutes, string $locale = 'fr'): string
    {
        if ($minutes < 60) {
            return $this->translate('time.minutes', $locale, ['count' => $minutes]);
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $this->translate('time.hours', $locale, ['count' => $hours]);
        }

        return $this->translate('time.hours_minutes', $locale, [
            'hours' => $hours,
            'minutes' => $remainingMinutes
        ]);
    }
}
