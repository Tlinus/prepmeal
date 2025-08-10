<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\SeasonalIngredientService;
use PrepMeal\Core\Services\TranslationService;

class IngredientController extends BaseController
{
    private SeasonalIngredientService $seasonalIngredientService;

    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        SeasonalIngredientService $seasonalIngredientService
    ) {
        parent::__construct($view, $translationService);
        $this->seasonalIngredientService = $seasonalIngredientService;
    }

    public function index(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $currentSeason = $this->seasonalIngredientService->getCurrentSeason();
        $seasonalIngredients = $this->seasonalIngredientService->getSeasonalIngredients($currentSeason);
        $allergens = $this->seasonalIngredientService->getAllAllergens();

        return $this->render($response, 'ingredients/index.twig', [
            'seasonalIngredients' => $seasonalIngredients,
            'allergens' => $allergens,
            'currentSeason' => $currentSeason,
            'locale' => $locale
        ]);
    }

    public function getSeasonalIngredients(Request $request, Response $response): Response
    {
        $season = $request->getQueryParams()['season'] ?? 'current';
        $allergens = $request->getQueryParams()['allergens'] ?? [];
        
        if ($season === 'current') {
            $season = $this->seasonalIngredientService->getCurrentSeason();
        }

        $ingredients = $this->seasonalIngredientService->getSeasonalIngredients($season, $allergens);

        return $this->jsonResponse($response, [
            'success' => true,
            'ingredients' => $ingredients,
            'season' => $season
        ]);
    }

    public function updatePreferences(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $userId = $this->getUserId($request);
        
        $preferences = [
            'selected_ingredients' => $data['selected_ingredients'] ?? [],
            'excluded_allergens' => $data['excluded_allergens'] ?? [],
            'diet_type' => $data['diet_type'] ?? 'equilibre',
            'servings' => $data['servings'] ?? 2,
            'period' => $data['period'] ?? 'week'
        ];

        // Sauvegarder les préférences utilisateur
        $this->seasonalIngredientService->saveUserPreferences($userId, $preferences);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Preferences updated successfully'
        ]);
    }
}

