<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends BaseController
{
    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        \PrepMeal\Core\Services\TranslationService $translation
    ) {
        parent::__construct($view, $translation);
    }

    public function index(Request $request, Response $response): Response
    {
        $authResponse = $this->requireAuth($request, $response);
        if ($authResponse) {
            return $authResponse;
        }

        $user = $this->getCurrentUser();
        $locale = $this->getLocale($request);

        $data = [
            'page_title' => $this->translation->translate('settings.title', $locale),
            'user' => $user,
            'flash_message' => $this->getFlashMessage()
        ];

        return $this->render($response, 'settings/index.twig', $data);
    }

    public function updateUnits(Request $request, Response $response): Response
    {
        $authResponse = $this->requireAuth($request, $response);
        if ($authResponse) {
            return $authResponse;
        }

        $data = $request->getParsedBody();
        $units = $data['units'] ?? 'metric';

        if (!in_array($units, ['metric', 'imperial'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Système d\'unités invalide'
            ], 400);
        }

        // Mettre à jour les préférences utilisateur
        $_SESSION['units'] = $units;

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Système d\'unités mis à jour avec succès'
        ]);
    }

    public function updateLocale(Request $request, Response $response): Response
    {
        $authResponse = $this->requireAuth($request, $response);
        if ($authResponse) {
            return $authResponse;
        }

        $data = $request->getParsedBody();
        $locale = $data['locale'] ?? 'fr';

        if (!in_array($locale, ['fr', 'en', 'es', 'de'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Locale invalide'
            ], 400);
        }

        // Mettre à jour la locale
        $_SESSION['locale'] = $locale;

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Locale mise à jour avec succès'
        ]);
    }
}
