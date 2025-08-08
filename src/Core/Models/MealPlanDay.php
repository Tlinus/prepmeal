<?php

declare(strict_types=1);

namespace PrepMeal\Core\Models;

class MealPlanDay
{
    public function __construct(
        private \DateTime $date,
        private array $meals
    ) {}

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function getMeals(): array
    {
        return $this->meals;
    }

    public function getMeal(string $mealType): ?Recipe
    {
        return $this->meals[$mealType] ?? null;
    }

    public function getBreakfast(): ?Recipe
    {
        return $this->getMeal('breakfast');
    }

    public function getLunch(): ?Recipe
    {
        return $this->getMeal('lunch');
    }

    public function getDinner(): ?Recipe
    {
        return $this->getMeal('dinner');
    }

    public function getDayOfWeek(): string
    {
        $days = [
            1 => 'lundi',
            2 => 'mardi', 
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            7 => 'dimanche'
        ];

        return $days[(int) $this->date->format('N')] ?? '';
    }

    public function getDayOfWeekEn(): string
    {
        $days = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday', 
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday'
        ];

        return $days[(int) $this->date->format('N')] ?? '';
    }

    public function isWeekend(): bool
    {
        $dayOfWeek = (int) $this->date->format('N');
        return $dayOfWeek >= 6;
    }

    public function isWeekday(): bool
    {
        return !$this->isWeekend();
    }

    public function getTotalPrepTime(): int
    {
        $total = 0;
        foreach ($this->meals as $meal) {
            $total += $meal->getTotalTime();
        }
        return $total;
    }

    public function getTotalCalories(): int
    {
        $total = 0;
        foreach ($this->meals as $meal) {
            $nutrition = $meal->getNutrition();
            $total += $nutrition['calories'] ?? 0;
        }
        return $total;
    }

    public function getNutritionalSummary(): array
    {
        $summary = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fat' => 0,
            'fiber' => 0
        ];

        foreach ($this->meals as $meal) {
            $nutrition = $meal->getNutrition();
            foreach ($summary as $key => $value) {
                $summary[$key] += $nutrition[$key] ?? 0;
            }
        }

        return $summary;
    }

    public function getAllergens(): array
    {
        $allergens = [];
        foreach ($this->meals as $meal) {
            $mealAllergens = $meal->getAllergens();
            foreach ($mealAllergens as $allergen) {
                if (!in_array($allergen, $allergens)) {
                    $allergens[] = $allergen;
                }
            }
        }
        return $allergens;
    }

    public function getDietaryRestrictions(): array
    {
        $restrictions = [];
        foreach ($this->meals as $meal) {
            $mealRestrictions = $meal->getDietaryRestrictions();
            foreach ($mealRestrictions as $restriction) {
                if (!in_array($restriction, $restrictions)) {
                    $restrictions[] = $restriction;
                }
            }
        }
        return $restrictions;
    }

    public function toArray(string $locale = 'fr'): array
    {
        $mealsArray = [];
        foreach ($this->meals as $mealType => $meal) {
            $mealsArray[$mealType] = $meal->toArray($locale);
        }

        return [
            'date' => $this->date->format('Y-m-d'),
            'day_of_week' => $this->getDayOfWeek(),
            'day_of_week_en' => $this->getDayOfWeekEn(),
            'is_weekend' => $this->isWeekend(),
            'is_weekday' => $this->isWeekday(),
            'meals' => $mealsArray,
            'total_prep_time' => $this->getTotalPrepTime(),
            'total_calories' => $this->getTotalCalories(),
            'nutritional_summary' => $this->getNutritionalSummary(),
            'allergens' => $this->getAllergens(),
            'dietary_restrictions' => $this->getDietaryRestrictions()
        ];
    }

    public function toJson(string $locale = 'fr'): string
    {
        return json_encode($this->toArray($locale), JSON_UNESCAPED_UNICODE);
    }
}
