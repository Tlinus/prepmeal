<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\MealPlanningService;
use PrepMeal\Core\Services\TranslationService;
use PrepMeal\Core\Services\SeasonalIngredientService;

class MealPlanningController extends BaseController
{
    private MealPlanningService $mealPlanningService;
    private TranslationService $translationService;
    private SeasonalIngredientService $seasonalService;

    public function __construct(
        MealPlanningService $mealPlanningService,
        TranslationService $translationService,
        SeasonalIngredientService $seasonalService
    ) {
        $this->mealPlanningService = $mealPlanningService;
        $this->translationService = $translationService;
        $this->seasonalService = $seasonalService;
    }

    public function index(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $seasonalIngredients = $this->seasonalService->getCurrentSeasonIngredients($locale);
        $dietTypes = $this->mealPlanningService->getDietTypes();
        $allergens = $this->mealPlanningService->getAllergens();
        $periods = $this->mealPlanningService->getPeriods();

        $data = [
            'seasonalIngredients' => $seasonalIngredients,
            'dietTypes' => $dietTypes,
            'allergens' => $allergens,
            'periods' => $periods,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['meal_planning', 'common'])
        ];

        return $this->render($response, 'meal_planning/index.twig', $data);
    }

    public function generatePlan(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();

        // Validation des données
        $preferences = $this->validatePreferences($data);
        
        try {
            $mealPlan = $this->mealPlanningService->generatePlan($preferences);
            
            // Sauvegarder le planning en base
            $this->mealPlanningService->savePlan($mealPlan, $userId);
            
            $planData = $mealPlan->toArray($locale);
            $nutritionalBalance = $this->mealPlanningService->calculateNutritionalBalance($mealPlan);
            $shoppingList = $this->mealPlanningService->generateShoppingList($mealPlan);

            return $this->jsonResponse($response, [
                'success' => true,
                'plan' => $planData,
                'nutritionalBalance' => $nutritionalBalance,
                'shoppingList' => $shoppingList,
                'message' => 'Planning généré avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la génération du planning: ' . $e->getMessage()
            ], 400);
        }
    }

    public function myPlans(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $plans = $this->mealPlanningService->getUserPlans($userId, $locale);

        $data = [
            'plans' => $plans,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['meal_planning', 'common'])
        ];

        return $this->render($response, 'meal_planning/my_plans.twig', $data);
    }

    public function showPlan(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        
        $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
        
        if (!$plan) {
            return $this->redirect($response, '/my-plans');
        }

        $nutritionalBalance = $this->mealPlanningService->calculateNutritionalBalance($plan);
        $shoppingList = $this->mealPlanningService->generateShoppingList($plan);

        $data = [
            'plan' => $plan,
            'nutritionalBalance' => $nutritionalBalance,
            'shoppingList' => $shoppingList,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['meal_planning', 'common'])
        ];

        return $this->render($response, 'meal_planning/show_plan.twig', $data);
    }

    public function updatePlan(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        $data = $request->getParsedBody();
        
        try {
            $updatedPlan = $this->mealPlanningService->updatePlan($planId, $userId, $data);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'plan' => $updatedPlan->toArray($this->getLocale($request)),
                'message' => 'Planning mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 400);
        }
    }

    public function deletePlan(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        
        try {
            $this->mealPlanningService->deletePlan($planId, $userId);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Planning supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 400);
        }
    }

    public function exportPlan(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        $format = $args['format'] ?? 'pdf';
        
        $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
        
        if (!$plan) {
            return $this->redirect($response, '/my-plans');
        }

        switch ($format) {
            case 'pdf':
                return $this->exportPlanAsPdf($response, $plan, $locale);
            case 'ical':
                return $this->exportPlanAsIcal($response, $plan, $locale);
            case 'csv':
                return $this->exportPlanAsCsv($response, $plan, $locale);
            default:
                return $this->redirect($response, '/plan/' . $planId);
        }
    }

    public function shoppingList(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        $planId = $args['id'];
        
        $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
        
        if (!$plan) {
            return $this->redirect($response, '/my-plans');
        }

        $shoppingList = $this->mealPlanningService->generateShoppingList($plan);

        $data = [
            'plan' => $plan,
            'shoppingList' => $shoppingList,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['meal_planning', 'common'])
        ];

        return $this->render($response, 'meal_planning/shopping_list.twig', $data);
    }

    private function validatePreferences(array $data): array
    {
        $preferences = [
            'period' => $data['period'] ?? 'week',
            'diet_type' => $data['diet_type'] ?? 'equilibre',
            'allergens' => $data['allergens'] ?? [],
            'max_prep_time' => isset($data['max_prep_time']) ? (int) $data['max_prep_time'] : null,
            'servings' => isset($data['servings']) ? (int) $data['servings'] : 2,
            'locale' => $data['locale'] ?? 'fr',
            'selected_ingredients' => $data['selected_ingredients'] ?? [],
            'excluded_ingredients' => $data['excluded_ingredients'] ?? []
        ];

        // Validation des valeurs
        $validPeriods = ['week', 'month', 'year'];
        if (!in_array($preferences['period'], $validPeriods)) {
            throw new \InvalidArgumentException('Période invalide');
        }

        $validDietTypes = ['prise_masse', 'equilibre', 'seche', 'anti_cholesterol', 'vegan', 'vegetarien', 'recettes_simples', 'cetogene', 'paleo', 'sans_gluten', 'mediterraneen'];
        if (!in_array($preferences['diet_type'], $validDietTypes)) {
            throw new \InvalidArgumentException('Type de régime invalide');
        }

        return $preferences;
    }

    private function exportPlanAsPdf(Response $response, $plan, string $locale): Response
    {
        // Implémentation de l'export PDF
        $content = $this->renderToString('meal_planning/export_pdf.twig', [
            'plan' => $plan,
            'locale' => $locale
        ]);

        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="planning_' . $plan->getId() . '.pdf"');
    }

    private function exportPlanAsIcal(Response $response, $plan, string $locale): Response
    {
        // Implémentation de l'export iCal
        $icalContent = $this->generateIcalContent($plan, $locale);

        $response->getBody()->write($icalContent);
        return $response
            ->withHeader('Content-Type', 'text/calendar')
            ->withHeader('Content-Disposition', 'attachment; filename="planning_' . $plan->getId() . '.ics"');
    }

    private function exportPlanAsCsv(Response $response, $plan, string $locale): Response
    {
        // Implémentation de l'export CSV
        $csvContent = $this->generateCsvContent($plan, $locale);

        $response->getBody()->write($csvContent);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="planning_' . $plan->getId() . '.csv"');
    }

    private function generateIcalContent($plan, string $locale): string
    {
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//PrepMeal//Meal Planning//FR\r\n";

        foreach ($plan->getDays() as $day) {
            foreach ($day->getMeals() as $meal) {
                $ical .= "BEGIN:VEVENT\r\n";
                $ical .= "UID:" . uniqid() . "@prepmeal.com\r\n";
                $ical .= "DTSTART:" . $day->getDate()->format('Ymd\THis\Z') . "\r\n";
                $ical .= "DTEND:" . $day->getDate()->format('Ymd\THis\Z') . "\r\n";
                $ical .= "SUMMARY:" . $meal->getTitle($locale) . "\r\n";
                $ical .= "DESCRIPTION:" . $meal->getDescription($locale) . "\r\n";
                $ical .= "END:VEVENT\r\n";
            }
        }

        $ical .= "END:VCALENDAR\r\n";
        return $ical;
    }

    private function generateCsvContent($plan, string $locale): string
    {
        $csv = "Date,Repas,Titre,Description,Ingrédients\r\n";
        
        foreach ($plan->getDays() as $day) {
            foreach ($day->getMeals() as $meal) {
                $ingredients = implode(', ', array_map(fn($ing) => $ing->getName($locale), $meal->getIngredients()));
                $csv .= sprintf(
                    '"%s","%s","%s","%s","%s"' . "\r\n",
                    $day->getDate()->format('Y-m-d'),
                    $meal->getCategory(),
                    $meal->getTitle($locale),
                    $meal->getDescription($locale),
                    $ingredients
                );
            }
        }
        
        return $csv;
    }
}
