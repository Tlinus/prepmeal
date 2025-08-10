<?php

declare(strict_types=1);

namespace PrepMeal\Core\Models;

class Recipe
{
    private bool $is_favorite = false;

    public function __construct(
        private string $id,
        private array $title,
        private array $description,
        private string $category,
        private array $season,
        private int $prepTime,
        private int $cookTime,
        private int $servings,
        private string $difficulty,
        private array $nutrition,
        private array $allergens,
        private array $dietaryRestrictions,
        private array $ingredients,
        private array $instructions,
        private array $tags,
        private ?string $imageUrl = null
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(string $locale = 'fr'): string
    {
        return $this->title[$locale] ?? $this->title['fr'] ?? '';
    }

    public function getTitles(): array
    {
        return $this->title;
    }

    public function getDescription(string $locale = 'fr'): string
    {
        return $this->description[$locale] ?? $this->description['fr'] ?? '';
    }

    public function getDescriptions(): array
    {
        return $this->description;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getSeason(): array
    {
        return $this->season;
    }

    public function getPrepTime(): int
    {
        return $this->prepTime;
    }

    public function getCookTime(): int
    {
        return $this->cookTime;
    }

    public function getTotalTime(): int
    {
        return $this->prepTime + $this->cookTime;
    }

    public function getServings(): int
    {
        return $this->servings;
    }

    public function getDifficulty(): string
    {
        return $this->difficulty;
    }

    public function getNutrition(): array
    {
        return $this->nutrition;
    }

    public function getAllergens(): array
    {
        return $this->allergens;
    }

    public function getDietaryRestrictions(): array
    {
        return $this->dietaryRestrictions;
    }

    public function getIngredients(): array
    {
        return $this->ingredients;
    }

    public function getInstructions(): array
    {
        return $this->instructions;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function isSeasonal(): bool
    {
        return !empty($this->season);
    }

    public function isVegan(): bool
    {
        return in_array('vegan', $this->dietaryRestrictions);
    }

    public function isVegetarian(): bool
    {
        return in_array('vegetarien', $this->dietaryRestrictions);
    }

    public function isGlutenFree(): bool
    {
        return !in_array('gluten', $this->allergens);
    }

    public function hasAllergen(string $allergen): bool
    {
        return in_array($allergen, $this->allergens);
    }

    public function isFavorite(): bool
    {
        return $this->is_favorite;
    }

    public function setIsFavorite(bool $isFavorite): void
    {
        $this->is_favorite = $isFavorite;
    }

    public function toArray(string $locale = 'fr'): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getTitle($locale),
            'description' => $this->getDescription($locale),
            'category' => $this->category,
            'season' => $this->season,
            'prep_time' => $this->prepTime,
            'cook_time' => $this->cookTime,
            'total_time' => $this->getTotalTime(),
            'servings' => $this->servings,
            'difficulty' => $this->difficulty,
            'nutrition' => is_array($this->nutrition) ? $this->nutrition : [],
            'allergens' => $this->allergens,
            'dietary_restrictions' => $this->dietaryRestrictions,
            'ingredients' => array_map(fn($ingredient) => $ingredient->toArray($locale), $this->ingredients),
            'instructions' => array_map(function($step) use ($locale) {
                if (is_array($step) && isset($step['text'])) {
                    if (is_array($step['text'])) {
                        return [
                            'step' => $step['step'] ?? 1,
                            'text' => $step['text'][$locale] ?? $step['text']['fr'] ?? $step['text']['en'] ?? ''
                        ];
                    } else {
                        return [
                            'step' => $step['step'] ?? 1,
                            'text' => $step['text']
                        ];
                    }
                }
                return $step;
            }, $this->instructions),
            'tags' => $this->tags,
            'image_url' => $this->imageUrl,
            'is_seasonal' => $this->isSeasonal(),
            'is_vegan' => $this->isVegan(),
            'is_vegetarian' => $this->isVegetarian(),
            'is_gluten_free' => $this->isGlutenFree(),
            'is_favorite' => $this->isFavorite(),
        ];
    }

    public function toJson(string $locale = 'fr'): string
    {
        return json_encode($this->toArray($locale), JSON_UNESCAPED_UNICODE);
    }
}
