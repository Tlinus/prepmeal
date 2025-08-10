<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProfileController extends BaseController
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
            'page_title' => $this->translation->translate('profile.title', $locale),
            'user' => $user,
            'flash_message' => $this->getFlashMessage()
        ];

        return $this->render($response, 'profile/index.twig', $data);
    }

    public function update(Request $request, Response $response): Response
    {
        $authResponse = $this->requireAuth($request, $response);
        if ($authResponse) {
            return $authResponse;
        }

        $rules = [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email',
            'locale' => 'required',
            'units' => 'required'
        ];

        $errors = $this->validateRequest($request, $rules);

        if (!empty($errors)) {
            $this->setFlashMessage('error', 'Veuillez corriger les erreurs dans le formulaire.');
            return $this->redirect($response, '/profile');
        }

        // Mettre à jour les préférences utilisateur
        $name = $this->getPostParam($request, 'name');
        $email = $this->getPostParam($request, 'email');
        $locale = $this->getPostParam($request, 'locale');
        $units = $this->getPostParam($request, 'units');
        $allergens = $this->getPostParam($request, 'allergens', []);
        $dietaryRestrictions = $this->getPostParam($request, 'dietary_restrictions', []);

        // Mettre à jour la session
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['locale'] = $locale;
        $_SESSION['units'] = $units;
        $_SESSION['allergens'] = $allergens;
        $_SESSION['dietary_restrictions'] = $dietaryRestrictions;

        $this->setFlashMessage('success', 'Profil mis à jour avec succès.');
        return $this->redirect($response, '/profile');
    }

    public function preferences(Request $request, Response $response): Response
    {
        $authResponse = $this->requireAuth($request, $response);
        if ($authResponse) {
            return $authResponse;
        }

        $user = $this->getCurrentUser();
        $locale = $this->getLocale($request);

        $data = [
            'page_title' => $this->translation->translate('profile.preferences', $locale),
            'user' => $user,
            'flash_message' => $this->getFlashMessage()
        ];

        return $this->render($response, 'profile/preferences.twig', $data);
    }

    public function updatePreferences(Request $request, Response $response): Response
    {
        $authResponse = $this->requireAuth($request, $response);
        if ($authResponse) {
            return $authResponse;
        }

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
        // Ici, vous devriez implémenter la sauvegarde en base de données

        $this->setFlashMessage('success', 'Préférences mises à jour avec succès.');
        return $this->redirect($response, '/profile/preferences');
    }
}
