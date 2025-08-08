<?php

declare(strict_types=1);

namespace PrepMeal\Core\Models;

class MealPlan
{
    public function __construct(
        private string $id,
        private \DateTime $startDate,
        private \DateTime $endDate,
        private array $days,
        private array $preferences
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStartDate(): \DateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTime
    {
        return $this->endDate;
    }

    public function getDays(): array
    {
        return $this->days;
    }

    public function getPreferences(): array
    {
        return $this->preferences;
    }

    public function getDuration(): int
    {
        $interval = $this->startDate->diff($this->endDate);
        return $interval->days;
    }

    public function getDayByDate(\DateTime $date): ?MealPlanDay
    {
        $dateString = $date->format('Y-m-d');
        
        foreach ($this->days as $day) {
            if ($day->getDate()->format('Y-m-d') === $dateString) {
                return $day;
            }
        }

        return null;
    }

    public function getMealsForDate(\DateTime $date): array
    {
        $day = $this->getDayByDate($date);
        return $day ? $day->getMeals() : [];
    }

    public function getTotalRecipes(): int
    {
        $total = 0;
        foreach ($this->days as $day) {
            $total += count($day->getMeals());
        }
        return $total;
    }

    public function getUniqueRecipes(): array
    {
        $recipes = [];
        foreach ($this->days as $day) {
            foreach ($day->getMeals() as $meal) {
                $recipes[$meal->getId()] = $meal;
            }
        }
        return array_values($recipes);
    }

    public function toArray(string $locale = 'fr'): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->startDate->format('Y-m-d'),
            'end_date' => $this->endDate->format('Y-m-d'),
            'duration' => $this->getDuration(),
            'days' => array_map(fn($day) => $day->toArray($locale), $this->days),
            'preferences' => $this->preferences,
            'total_recipes' => $this->getTotalRecipes(),
            'unique_recipes_count' => count($this->getUniqueRecipes())
        ];
    }

    public function toJson(string $locale = 'fr'): string
    {
        return json_encode($this->toArray($locale), JSON_UNESCAPED_UNICODE);
    }

    public function exportToCalendar(): string
    {
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//PrepMeal//Meal Planner//FR\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";

        foreach ($this->days as $day) {
            foreach ($day->getMeals() as $mealType => $recipe) {
                $date = $day->getDate()->format('Ymd');
                $title = $recipe->getTitle('fr');
                
                $ical .= "BEGIN:VEVENT\r\n";
                $ical .= "UID:" . uniqid() . "@prepmeal.com\r\n";
                $ical .= "DTSTART;VALUE=DATE:" . $date . "\r\n";
                $ical .= "DTEND;VALUE=DATE:" . $date . "\r\n";
                $ical .= "SUMMARY:" . ucfirst($mealType) . " - " . $title . "\r\n";
                $ical .= "DESCRIPTION:" . $recipe->getDescription('fr') . "\r\n";
                $ical .= "END:VEVENT\r\n";
            }
        }

        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }
}
