<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PrepMeal\Core\Services\RecipeService;
use PrepMeal\Core\Services\SeasonalIngredientService;

class HomeController extends BaseController
{
    private RecipeService $recipeService;
    private SeasonalIngredientService $seasonalService;

    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        \PrepMeal\Core\Services\TranslationService $translation,
        RecipeService $recipeService,
        SeasonalIngredientService $seasonalService
    ) {
        parent::__construct($view, $translation);
        $this->recipeService = $recipeService;
        $this->seasonalService = $seasonalService;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->getDefaultLocale();
        
        // Récupérer quelques recettes populaires
        $popularRecipes = $this->recipeService->getPopularRecipes(6);
        
        // Récupérer les ingrédients de saison actuels
        $currentSeason = $this->seasonalService->getCurrentSeason();
        $seasonalIngredients = $this->seasonalService->getCurrentSeasonalIngredients();
        
        // Suggestions de recettes de saison
        $seasonalSuggestions = $this->seasonalService->getSeasonalRecipeSuggestions($currentSeason);

        $data = [
            'page_title' => $this->translation->translate('home.title', $locale),
            'hero_title' => $this->translation->translate('home.hero_title', $locale),
            'hero_subtitle' => $this->translation->translate('home.hero_subtitle', $locale),
            'cta_generate' => $this->translation->translate('home.cta_generate', $locale),
            'cta_browse' => $this->translation->translate('home.cta_browse', $locale),
            'features' => [
                'seasonal' => $this->translation->translate('home.features.seasonal', $locale),
                'personalized' => $this->translation->translate('home.features.personalized', $locale),
                'healthy' => $this->translation->translate('home.features.healthy', $locale),
                'easy' => $this->translation->translate('home.features.easy', $locale)
            ],
            'popular_recipes' => $popularRecipes,
            'current_season' => $currentSeason,
            'seasonal_ingredients' => $seasonalIngredients,
            'seasonal_suggestions' => $seasonalSuggestions,
            'flash_message' => $this->getFlashMessage()
        ];

        return $this->render($response, 'home/index.twig', $data);
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $authResponse = $this->requireAuth($request, $response);
        if ($authResponse) {
            return $authResponse;
        }

        $locale = $this->getDefaultLocale();
        $user = $this->getCurrentUser();

        // Récupérer les plannings récents de l'utilisateur
        $recentPlans = []; // À implémenter avec MealPlanningService
        
        // Récupérer les recettes favorites
        $favoriteRecipes = $this->recipeService->getUserFavorites($user['id']);
        
        // Statistiques de l'utilisateur
        $stats = [
            'total_plans' => 0, // À implémenter
            'total_recipes_cooked' => 0, // À implémenter
            'favorite_recipes_count' => count($favoriteRecipes)
        ];

        $data = [
            'page_title' => 'Tableau de bord',
            'user' => $user,
            'recent_plans' => $recentPlans,
            'favorite_recipes' => $favoriteRecipes,
            'stats' => $stats
        ];

        return $this->render($response, 'dashboard/index.twig', $data);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->isAuthenticated()) {
            return $this->redirect($response, '/dashboard');
        }

        $data = [
            'page_title' => $this->translation->translate('navigation.login', $this->getDefaultLocale()),
            'flash_message' => $this->getFlashMessage()
        ];

        return $this->render($response, 'auth/login.twig', $data);
    }

    public function loginPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ];

        $errors = $this->validateRequest($request, $rules);

        if (!empty($errors)) {
            $this->setFlashMessage('error', 'Veuillez corriger les erreurs dans le formulaire.');
            return $this->render($response, 'auth/login.twig', [
                'errors' => $errors,
                'old_data' => $request->getParsedBody()
            ]);
        }

        $email = $this->getPostParam($request, 'email');
        $password = $this->getPostParam($request, 'password');

        // Ici, vous devriez implémenter la logique d'authentification
        // Pour l'exemple, on simule une authentification réussie
        if ($email === 'demo@example.com' && $password === 'password') {
            $_SESSION['user'] = [
                'id' => 1,
                'email' => $email,
                'name' => 'Utilisateur Demo'
            ];
            
            $this->setFlashMessage('success', 'Connexion réussie !');
            return $this->redirect($response, '/dashboard');
        }

        $this->setFlashMessage('error', 'Email ou mot de passe incorrect.');
        return $this->redirect($response, '/login');
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->isAuthenticated()) {
            return $this->redirect($response, '/dashboard');
        }

        $data = [
            'page_title' => $this->translation->translate('navigation.register', $this->getDefaultLocale()),
            'flash_message' => $this->getFlashMessage()
        ];

        return $this->render($response, 'auth/register.twig', $data);
    }

    public function registerPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'password_confirm' => 'required'
        ];

        $errors = $this->validateRequest($request, $rules);

        // Vérifier que les mots de passe correspondent
        $password = $this->getPostParam($request, 'password');
        $passwordConfirm = $this->getPostParam($request, 'password_confirm');
        
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            $this->setFlashMessage('error', 'Veuillez corriger les erreurs dans le formulaire.');
            return $this->render($response, 'auth/register.twig', [
                'errors' => $errors,
                'old_data' => $request->getParsedBody()
            ]);
        }

        // Ici, vous devriez implémenter la logique d'inscription
        // Pour l'exemple, on simule une inscription réussie
        $name = $this->getPostParam($request, 'name');
        $email = $this->getPostParam($request, 'email');

        $_SESSION['user'] = [
            'id' => 2,
            'email' => $email,
            'name' => $name
        ];

        $this->setFlashMessage('success', 'Inscription réussie ! Bienvenue sur PrepMeal.');
        return $this->redirect($response, '/dashboard');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        session_destroy();
        $this->setFlashMessage('success', 'Vous avez été déconnecté avec succès.');
        return $this->redirect($response, '/');
    }

    public function profile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $authResponse = $this->requireAuth($request, $response);
        if ($authResponse) {
            return $authResponse;
        }

        $user = $this->getCurrentUser();
        $locale = $this->getDefaultLocale();

        $data = [
            'page_title' => $this->translation->translate('profile.title', $locale),
            'user' => $user,
            'flash_message' => $this->getFlashMessage()
        ];

        return $this->render($response, 'profile/index.twig', $data);
    }

    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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

        $this->setFlashMessage('success', $this->translation->translate('profile.changes_saved', $locale));
        return $this->redirect($response, '/profile');
    }
}
