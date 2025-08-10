<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

use PrepMeal\Core\Models\MealPlan;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfExportService
{
    private TranslationService $translationService;
    private UnitConversionService $unitService;

    public function __construct(
        TranslationService $translationService,
        UnitConversionService $unitService
    ) {
        $this->translationService = $translationService;
        $this->unitService = $unitService;
    }

    public function exportMealPlan(MealPlan $plan, string $locale = 'fr'): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $html = $this->generateMealPlanHtml($plan, $locale);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function generateMealPlanHtml(MealPlan $plan, string $locale): string
    {
        $translations = $this->translationService->getTranslations($locale, ['meal_planning', 'common']);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . $translations['meal_planning']['title'] . '</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                .header {
                    text-align: center;
                    border-bottom: 2px solid #4CAF50;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .header h1 {
                    color: #4CAF50;
                    margin: 0;
                    font-size: 24px;
                }
                .plan-info {
                    background: #f9f9f9;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .day-section {
                    margin-bottom: 30px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    overflow: hidden;
                }
                .day-header {
                    background: #4CAF50;
                    color: white;
                    padding: 10px 15px;
                    font-weight: bold;
                }
                .meal {
                    padding: 15px;
                    border-bottom: 1px solid #eee;
                }
                .meal:last-child {
                    border-bottom: none;
                }
                .meal-title {
                    font-weight: bold;
                    color: #4CAF50;
                    margin-bottom: 5px;
                }
                .meal-details {
                    font-size: 12px;
                    color: #666;
                    margin-bottom: 10px;
                }
                .ingredients {
                    font-size: 11px;
                    color: #555;
                }
                .nutrition-info {
                    background: #e8f5e8;
                    padding: 10px;
                    border-radius: 3px;
                    margin-top: 10px;
                    font-size: 11px;
                }
                .shopping-list {
                    margin-top: 30px;
                    border-top: 2px solid #4CAF50;
                    padding-top: 20px;
                }
                .shopping-list h2 {
                    color: #4CAF50;
                    margin-bottom: 15px;
                }
                .shopping-item {
                    padding: 5px 0;
                    border-bottom: 1px solid #eee;
                }
                .seasonal-badge {
                    background: #ff9800;
                    color: white;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 10px;
                    margin-left: 10px;
                }
            </style>
        </head>
        <body>';

        // En-tête
        $html .= '
        <div class="header">
            <h1>' . $translations['meal_planning']['title'] . '</h1>
            <p>' . $plan->getStartDate()->format('d/m/Y') . ' - ' . $plan->getEndDate()->format('d/m/Y') . '</p>
        </div>';

        // Informations du planning
        $html .= '
        <div class="plan-info">
            <h3>' . $translations['meal_planning']['plan_info'] . '</h3>
            <p><strong>' . $translations['meal_planning']['period']['label'] . ':</strong> ' . 
            $translations['meal_planning']['period'][$plan->getPreferences()['period'] ?? 'week'] . '</p>
            <p><strong>' . $translations['meal_planning']['diet_type']['label'] . ':</strong> ' . 
            $translations['meal_planning']['diet_type'][$plan->getPreferences()['diet_type'] ?? 'equilibre'] . '</p>
        </div>';

        // Jours du planning
        foreach ($plan->getDays() as $day) {
            $html .= $this->generateDayHtml($day, $translations, $locale);
        }

        // Liste de courses
        $shoppingList = $this->generateShoppingList($plan, $locale);
        if (!empty($shoppingList)) {
            $html .= $this->generateShoppingListHtml($shoppingList, $translations);
        }

        $html .= '
        </body>
        </html>';

        return $html;
    }

    private function generateDayHtml(MealPlanDay $day, array $translations, string $locale): string
    {
        $date = $day->getDate();
        $dayName = $this->getDayName($date, $locale);
        
        $html = '
        <div class="day-section">
            <div class="day-header">
                ' . $dayName . ' - ' . $date->format('d/m/Y') . '
            </div>';

        $meals = $day->getMeals();
        $mealTypes = ['breakfast' => 'Petit-déjeuner', 'lunch' => 'Déjeuner', 'dinner' => 'Dîner'];

        foreach ($mealTypes as $mealType => $mealLabel) {
            if (isset($meals[$mealType])) {
                $recipe = $meals[$mealType];
                $html .= $this->generateMealHtml($recipe, $mealLabel, $translations, $locale);
            }
        }

        $html .= '</div>';
        return $html;
    }

    private function generateMealHtml(Recipe $recipe, string $mealLabel, array $translations, string $locale): string
    {
        $html = '
        <div class="meal">
            <div class="meal-title">' . $mealLabel . ': ' . $recipe->getTitle($locale) . '</div>
            <div class="meal-details">
                <strong>' . $translations['common']['prep_time'] . ':</strong> ' . $recipe->getPrepTime() . ' min | 
                <strong>' . $translations['common']['cook_time'] . ':</strong> ' . $recipe->getCookTime() . ' min | 
                <strong>' . $translations['common']['servings'] . ':</strong> ' . $recipe->getServings() . '
            </div>';

        // Ingrédients
        $html .= '<div class="ingredients"><strong>' . $translations['common']['ingredients'] . ':</strong><br>';
        foreach ($recipe->getIngredients() as $ingredient) {
            $name = $ingredient['name'][$locale] ?? $ingredient['name']['fr'] ?? '';
            $quantity = $this->unitService->formatQuantity($ingredient['quantity'], 'metric', $locale);
            $html .= '• ' . $quantity . ' ' . $name;
            
            if ($ingredient['seasonal'] ?? false) {
                $html .= ' <span class="seasonal-badge">' . $translations['common']['seasonal'] . '</span>';
            }
            $html .= '<br>';
        }
        $html .= '</div>';

        // Informations nutritionnelles
        $nutrition = $recipe->getNutrition();
        if (!empty($nutrition)) {
            $html .= '
            <div class="nutrition-info">
                <strong>' . $translations['common']['nutrition'] . ':</strong> 
                ' . ($nutrition['calories'] ?? 0) . ' kcal | 
                ' . ($nutrition['protein'] ?? 0) . 'g ' . $translations['common']['protein'] . ' | 
                ' . ($nutrition['carbs'] ?? 0) . 'g ' . $translations['common']['carbs'] . ' | 
                ' . ($nutrition['fat'] ?? 0) . 'g ' . $translations['common']['fat'] . '
            </div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function generateShoppingListHtml(array $shoppingList, array $translations): string
    {
        $html = '
        <div class="shopping-list">
            <h2>' . $translations['meal_planning']['shopping_list'] . '</h2>';

        foreach ($shoppingList as $ingredient => $details) {
            $quantity = $this->unitService->formatQuantity($details['quantity'], 'metric', 'fr');
            $html .= '
            <div class="shopping-item">
                <strong>' . $ingredient . '</strong> - ' . $quantity;
            
            if ($details['seasonal']) {
                $html .= ' <span class="seasonal-badge">' . $translations['common']['seasonal'] . '</span>';
            }
            
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function generateShoppingList(MealPlan $plan, string $locale): array
    {
        $shoppingList = [];

        foreach ($plan->getDays() as $day) {
            foreach ($day->getMeals() as $meal) {
                foreach ($meal->getIngredients() as $ingredient) {
                    $name = $ingredient['name'][$locale] ?? $ingredient['name']['fr'] ?? '';
                    $quantity = $ingredient['quantity'];

                    if (!isset($shoppingList[$name])) {
                        $shoppingList[$name] = [
                            'quantity' => $quantity,
                            'seasonal' => $ingredient['seasonal'] ?? false,
                            'season' => $ingredient['season'] ?? null
                        ];
                    } else {
                        // Additionner les quantités
                        $currentQuantity = $shoppingList[$name]['quantity'];
                        $shoppingList[$name]['quantity'] = $this->unitService->addQuantities($currentQuantity, $quantity);
                    }
                }
            }
        }

        return $shoppingList;
    }

    private function getDayName(\DateTime $date, string $locale): string
    {
        $dayNames = [
            'fr' => [
                1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
                5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'
            ],
            'en' => [
                1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
                5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
            ],
            'es' => [
                1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
            ],
            'de' => [
                1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
                5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'
            ]
        ];

        $dayOfWeek = (int) $date->format('N');
        return $dayNames[$locale][$dayOfWeek] ?? $dayNames['fr'][$dayOfWeek];
    }
}

