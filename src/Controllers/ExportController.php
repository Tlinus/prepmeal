<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\PdfExportService;
use PrepMeal\Core\Services\MealPlanningService;
use PrepMeal\Core\Services\StripeSubscriptionService;
use PrepMeal\Core\Services\TranslationService;

class ExportController extends BaseController
{
    private PdfExportService $pdfService;
    private MealPlanningService $mealPlanningService;
    private StripeSubscriptionService $stripeService;
    private TranslationService $translationService;

    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        PdfExportService $pdfService,
        MealPlanningService $mealPlanningService,
        StripeSubscriptionService $stripeService
    ) {
        parent::__construct($view, $translationService);
        $this->pdfService = $pdfService;
        $this->mealPlanningService = $mealPlanningService;
        $this->stripeService = $stripeService;
        $this->translationService = $translationService;
    }

    public function exportMealPlan(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        $planId = $args['id'] ?? '';

        // Vérifier les permissions d'export
        if (!$this->stripeService->canUserAccessFeature($userId, 'export_pdf')) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Fonctionnalité réservée aux abonnés premium'
            ], 403);
        }

        try {
            // Récupérer le planning
            $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
            
            if (!$plan) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Planning non trouvé'
                ], 404);
            }

            // Générer le PDF
            $pdfContent = $this->pdfService->exportMealPlan($plan, $locale);

            // Retourner le PDF
            $response = $response->withHeader('Content-Type', 'application/pdf');
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="meal-plan-' . $planId . '.pdf"');
            $response = $response->withHeader('Content-Length', strlen($pdfContent));

            $response->getBody()->write($pdfContent);
            return $response;

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportShoppingList(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        $planId = $args['id'] ?? '';

        // Vérifier les permissions d'export
        if (!$this->stripeService->canUserAccessFeature($userId, 'export_pdf')) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Fonctionnalité réservée aux abonnés premium'
            ], 403);
        }

        try {
            // Récupérer le planning
            $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
            
            if (!$plan) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Planning non trouvé'
                ], 404);
            }

            // Générer la liste de courses
            $shoppingList = $this->mealPlanningService->generateShoppingList($plan, $locale);

            // Générer le PDF de la liste de courses
            $pdfContent = $this->pdfService->exportShoppingList($shoppingList, $plan, $locale);

            // Retourner le PDF
            $response = $response->withHeader('Content-Type', 'application/pdf');
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="shopping-list-' . $planId . '.pdf"');
            $response = $response->withHeader('Content-Length', strlen($pdfContent));

            $response->getBody()->write($pdfContent);
            return $response;

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }

    public function previewMealPlan(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        $planId = $args['id'] ?? '';

        try {
            // Récupérer le planning
            $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
            
            if (!$plan) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Planning non trouvé'
                ], 404);
            }

            // Générer le HTML de prévisualisation
            $html = $this->pdfService->generateMealPlanHtml($plan, $locale);

            return $this->jsonResponse($response, [
                'success' => true,
                'html' => $html
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la prévisualisation: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportMultiplePlans(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();

        $planIds = $data['plan_ids'] ?? [];

        // Vérifier les permissions d'export
        if (!$this->stripeService->canUserAccessFeature($userId, 'export_pdf')) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Fonctionnalité réservée aux abonnés premium'
            ], 403);
        }

        if (empty($planIds)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Aucun planning sélectionné'
            ], 400);
        }

        try {
            $plans = [];
            foreach ($planIds as $planId) {
                $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
                if ($plan) {
                    $plans[] = $plan;
                }
            }

            if (empty($plans)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Aucun planning valide trouvé'
                ], 404);
            }

            // Générer le PDF combiné
            $pdfContent = $this->pdfService->exportMultiplePlans($plans, $locale);

            // Retourner le PDF
            $response = $response->withHeader('Content-Type', 'application/pdf');
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="multiple-meal-plans.pdf"');
            $response = $response->withHeader('Content-Length', strlen($pdfContent));

            $response->getBody()->write($pdfContent);
            return $response;

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportNutritionalReport(Request $request, Response $response, array $args): Response
    {
        $locale = $this->getDefaultLocale();
        $userId = $this->getUserId($request);
        $planId = $args['id'] ?? '';

        // Vérifier les permissions d'export
        if (!$this->stripeService->canUserAccessFeature($userId, 'export_pdf')) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Fonctionnalité réservée aux abonnés premium'
            ], 403);
        }

        try {
            // Récupérer le planning
            $plan = $this->mealPlanningService->getPlan($planId, $userId, $locale);
            
            if (!$plan) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Planning non trouvé'
                ], 404);
            }

            // Calculer l'équilibre nutritionnel
            $nutritionalBalance = $this->mealPlanningService->calculateNutritionalBalance($plan);

            // Générer le rapport nutritionnel
            $pdfContent = $this->pdfService->exportNutritionalReport($plan, $nutritionalBalance, $locale);

            // Retourner le PDF
            $response = $response->withHeader('Content-Type', 'application/pdf');
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="nutritional-report-' . $planId . '.pdf"');
            $response = $response->withHeader('Content-Length', strlen($pdfContent));

            $response->getBody()->write($pdfContent);
            return $response;

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }
}

