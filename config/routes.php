<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use PrepMeal\Controllers\HomeController;
use PrepMeal\Controllers\RecipeController;
use PrepMeal\Controllers\MealPlanningController;
use PrepMeal\Controllers\SubscriptionController;
use PrepMeal\Controllers\ApiController;
use PrepMeal\Middleware\AuthMiddleware;

return function (App $app) {
    // Routes publiques
    $app->get('/', [HomeController::class, 'index']);
    $app->get('/recipes', [RecipeController::class, 'index']);
    $app->get('/recipes/search', [RecipeController::class, 'search']);
    $app->get('/recipes/category/{category}', [RecipeController::class, 'byCategory']);
    $app->get('/recipes/seasonal', [RecipeController::class, 'seasonal']);
    $app->get('/recipes/{id}', [RecipeController::class, 'show']);
    $app->get('/meal-planning', [MealPlanningController::class, 'index']);
    $app->get('/subscription', [SubscriptionController::class, 'index']);
    
    // Routes d'authentification
    $app->get('/login', [HomeController::class, 'login']);
    $app->post('/login', [HomeController::class, 'loginPost']);
    $app->get('/register', [HomeController::class, 'register']);
    $app->post('/register', [HomeController::class, 'registerPost']);
    $app->get('/logout', [HomeController::class, 'logout']);

    // Routes protégées (nécessitent une authentification)
    $app->group('', function (RouteCollectorProxy $group) {
        $group->get('/dashboard', [HomeController::class, 'dashboard']);
        $group->get('/profile', [HomeController::class, 'profile']);
        $group->post('/profile', [HomeController::class, 'updateProfile']);
        
        // Gestion des plannings
        $group->get('/my-plans', [MealPlanningController::class, 'myPlans']);
        $group->post('/generate-plan', [MealPlanningController::class, 'generatePlan']);
        $group->get('/plan/{id}', [MealPlanningController::class, 'showPlan']);
        $group->post('/plan/{id}/update', [MealPlanningController::class, 'updatePlan']);
        $group->delete('/plan/{id}', [MealPlanningController::class, 'deletePlan']);
        $group->get('/plan/{id}/export/{format}', [MealPlanningController::class, 'exportPlan']);
        $group->get('/plan/{id}/shopping-list', [MealPlanningController::class, 'shoppingList']);
        
        // Gestion des abonnements
        $group->get('/subscription/manage', [SubscriptionController::class, 'manage']);
        $group->post('/subscription/create', [SubscriptionController::class, 'create']);
        $group->post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
        $group->get('/subscription/history', [SubscriptionController::class, 'history']);
        
        // Favoris
        $group->post('/recipes/{id}/favorite', [RecipeController::class, 'toggleFavorite']);
        $group->get('/favorites', [RecipeController::class, 'favorites']);
        
    })->add(AuthMiddleware::class);

    // API Routes
    $app->group('/api', function (RouteCollectorProxy $group) {
        // Routes publiques de l'API
        $group->get('/recipes', [ApiController::class, 'getRecipes']);
        $group->get('/recipes/search', [ApiController::class, 'searchRecipes']);
        $group->get('/recipes/{id}', [ApiController::class, 'getRecipe']);
        $group->get('/seasonal-ingredients', [ApiController::class, 'getSeasonalIngredients']);
        $group->get('/current-season-ingredients', [ApiController::class, 'getCurrentSeasonIngredients']);
        $group->get('/diet-types', [ApiController::class, 'getDietTypes']);
        $group->get('/allergens', [ApiController::class, 'getAllergens']);
        $group->get('/categories', [ApiController::class, 'getCategories']);
        $group->get('/difficulties', [ApiController::class, 'getDifficulties']);
        $group->get('/seasons', [ApiController::class, 'getSeasons']);
        $group->get('/translations', [ApiController::class, 'getTranslations']);
        $group->get('/units', [ApiController::class, 'getUnits']);
        
        // Routes protégées de l'API
        $group->group('', function (RouteCollectorProxy $subGroup) {
            $subGroup->post('/generate-plan', [ApiController::class, 'generatePlan']);
            $subGroup->get('/my-plans', [ApiController::class, 'getMyPlans']);
            $subGroup->post('/plans/{id}', [ApiController::class, 'updatePlan']);
            $subGroup->delete('/plans/{id}', [ApiController::class, 'deletePlan']);
            $subGroup->post('/favorites/{recipeId}', [ApiController::class, 'toggleFavorite']);
            $subGroup->get('/favorites', [ApiController::class, 'getFavorites']);
        })->add(AuthMiddleware::class);
    });

    // Webhooks Stripe
    $app->post('/webhooks/stripe', [SubscriptionController::class, 'stripeWebhook']);
};
