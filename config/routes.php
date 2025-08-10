<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    // Routes principales
    $app->get('/', \PrepMeal\Controllers\HomeController::class . ':index');
    $app->get('/home', \PrepMeal\Controllers\HomeController::class . ':index');
    $app->get('/dashboard', \PrepMeal\Controllers\HomeController::class . ':dashboard');
    
    // Routes de planification de repas
    $app->get('/meal-planning', \PrepMeal\Controllers\MealPlanningController::class . ':index');
    $app->post('/meal-planning/generate', \PrepMeal\Controllers\MealPlanningController::class . ':generatePlan');
    $app->get('/meal-planning/my-plans', \PrepMeal\Controllers\MealPlanningController::class . ':myPlans');
    $app->get('/meal-planning/{id}', \PrepMeal\Controllers\MealPlanningController::class . ':showPlan');
    $app->put('/meal-planning/{id}', \PrepMeal\Controllers\MealPlanningController::class . ':updatePlan');
    $app->delete('/meal-planning/{id}', \PrepMeal\Controllers\MealPlanningController::class . ':deletePlan');
    $app->get('/meal-planning/{id}/shopping-list', \PrepMeal\Controllers\MealPlanningController::class . ':shoppingList');
    $app->post('/meal-planning/save-preferences', \PrepMeal\Controllers\MealPlanningController::class . ':savePreferences');
    
    // Routes d'export
    $app->get('/export/meal-plan/{id}', \PrepMeal\Controllers\ExportController::class . ':exportMealPlan');
    $app->get('/export/shopping-list/{id}', \PrepMeal\Controllers\ExportController::class . ':exportShoppingList');
    $app->get('/export/nutritional-report/{id}', \PrepMeal\Controllers\ExportController::class . ':exportNutritionalReport');
    $app->post('/export/multiple-plans', \PrepMeal\Controllers\ExportController::class . ':exportMultiplePlans');
    $app->get('/export/preview/{id}', \PrepMeal\Controllers\ExportController::class . ':previewMealPlan');
    
    // Routes d'abonnement
    $app->get('/subscription', \PrepMeal\Controllers\SubscriptionController::class . ':index');
    $app->get('/subscription/plans', \PrepMeal\Controllers\SubscriptionController::class . ':plans');
    $app->get('/subscription/billing', \PrepMeal\Controllers\SubscriptionController::class . ':billing');
    $app->post('/subscription/create', \PrepMeal\Controllers\SubscriptionController::class . ':createSubscription');
    $app->post('/subscription/webhook', \PrepMeal\Controllers\SubscriptionController::class . ':webhook');
    $app->get('/subscription/{id}', \PrepMeal\Controllers\SubscriptionController::class . ':getSubscription');
    $app->post('/subscription/{id}/cancel', \PrepMeal\Controllers\SubscriptionController::class . ':cancelSubscription');
    $app->post('/subscription/{id}/reactivate', \PrepMeal\Controllers\SubscriptionController::class . ':reactivateSubscription');
    
    // Routes de recettes
    $app->get('/recipes', \PrepMeal\Controllers\RecipeController::class . ':index');
    $app->get('/recipes/search', \PrepMeal\Controllers\RecipeController::class . ':search');
    $app->get('/recipes/{id}', \PrepMeal\Controllers\RecipeController::class . ':show');
    $app->post('/recipes/{id}/favorite', \PrepMeal\Controllers\RecipeController::class . ':addToFavorites');
    $app->delete('/recipes/{id}/favorite', \PrepMeal\Controllers\RecipeController::class . ':removeFromFavorites');
    $app->get('/favorites', \PrepMeal\Controllers\RecipeController::class . ':favorites');
    $app->get('/my-plans', \PrepMeal\Controllers\MealPlanningController::class . ':myPlans');
    $app->get('/plan/{id}', \PrepMeal\Controllers\MealPlanningController::class . ':showPlan');
    
    // Routes API
    $app->group('/api', function (RouteCollectorProxy $group) {
        $group->get('/recipes', \PrepMeal\Controllers\ApiController::class . ':getRecipes');
        $group->get('/recipes/{id}', \PrepMeal\Controllers\ApiController::class . ':getRecipe');
        $group->post('/meal-planning/generate', \PrepMeal\Controllers\ApiController::class . ':generatePlan');
        $group->get('/meal-planning/my-plans', \PrepMeal\Controllers\ApiController::class . ':getMyPlans');
        $group->get('/seasonal-ingredients', \PrepMeal\Controllers\ApiController::class . ':getSeasonalIngredients');
        $group->get('/allergens', \PrepMeal\Controllers\ApiController::class . ':getAllergens');
        $group->get('/diet-types', \PrepMeal\Controllers\ApiController::class . ':getDietTypes');
        $group->get('/periods', \PrepMeal\Controllers\ApiController::class . ':getPeriods');
        $group->post('/meal-planning/save-preferences', \PrepMeal\Controllers\ApiController::class . ':savePreferences');
        $group->get('/meal-planning/{id}', \PrepMeal\Controllers\ApiController::class . ':getPlan');
        $group->put('/meal-planning/{id}', \PrepMeal\Controllers\ApiController::class . ':updatePlan');
        $group->delete('/meal-planning/{id}', \PrepMeal\Controllers\ApiController::class . ':deletePlan');
        $group->get('/meal-planning/{id}/shopping-list', \PrepMeal\Controllers\ApiController::class . ':getShoppingList');
        $group->get('/meal-planning/{id}/nutritional-balance', \PrepMeal\Controllers\ApiController::class . ':getNutritionalBalance');
    });
    
    // Routes d'authentification
    $app->get('/login', \PrepMeal\Controllers\AuthController::class . ':loginForm');
    $app->post('/login', \PrepMeal\Controllers\AuthController::class . ':login');
    $app->get('/register', \PrepMeal\Controllers\AuthController::class . ':registerForm');
    $app->post('/register', \PrepMeal\Controllers\AuthController::class . ':register');
    $app->post('/logout', \PrepMeal\Controllers\AuthController::class . ':logout');
    
    // Routes de profil
    $app->get('/profile', \PrepMeal\Controllers\ProfileController::class . ':index');
    $app->put('/profile', \PrepMeal\Controllers\ProfileController::class . ':update');
    $app->get('/profile/preferences', \PrepMeal\Controllers\ProfileController::class . ':preferences');
    $app->put('/profile/preferences', \PrepMeal\Controllers\ProfileController::class . ':updatePreferences');
    
    // Routes de paramètres
    $app->get('/settings', \PrepMeal\Controllers\SettingsController::class . ':index');
    $app->put('/settings/units', \PrepMeal\Controllers\SettingsController::class . ':updateUnits');
    $app->put('/settings/locale', \PrepMeal\Controllers\SettingsController::class . ':updateLocale');
    
    // Routes d'erreur
    $app->get('/404', \PrepMeal\Controllers\ErrorController::class . ':notFound');
    $app->get('/500', \PrepMeal\Controllers\ErrorController::class . ':serverError');
};
