<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use PrepMeal\Core\Database\DatabaseConnection;
use PrepMeal\Core\Database\RecipeRepository;
use PrepMeal\Core\Database\UserRepository;
use PrepMeal\Core\Database\SubscriptionRepository;
use PrepMeal\Core\Services\RecipeService;
use PrepMeal\Core\Services\MealPlanningService;
use PrepMeal\Core\Services\SubscriptionService;
use PrepMeal\Core\Services\TranslationService;
use PrepMeal\Core\Services\SeasonalIngredientService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Stripe\StripeClient;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        // Configuration de la base de données
        DatabaseConnection::class => function () {
            return new DatabaseConnection(
                $_ENV['DB_HOST'] ?? 'localhost',
                $_ENV['DB_NAME'] ?? 'prepmeal',
                $_ENV['DB_USER'] ?? 'root',
                $_ENV['DB_PASS'] ?? ''
            );
        },

        // Repositories
        RecipeRepository::class => function (DatabaseConnection $db) {
            return new RecipeRepository($db);
        },

        UserRepository::class => function (DatabaseConnection $db) {
            return new UserRepository($db);
        },

        SubscriptionRepository::class => function (DatabaseConnection $db) {
            return new SubscriptionRepository($db);
        },

        // Services
        RecipeService::class => function (RecipeRepository $recipeRepo) {
            return new RecipeService($recipeRepo);
        },

        MealPlanningService::class => function (
            RecipeRepository $recipeRepo,
            SeasonalIngredientService $seasonalService
        ) {
            return new MealPlanningService($recipeRepo, $seasonalService);
        },

        RecipeService::class => function (RecipeRepository $recipeRepo) {
            return new RecipeService($recipeRepo);
        },

        SubscriptionService::class => function (DatabaseConnection $db) {
            return new SubscriptionService($db);
        },

        TranslationService::class => function () {
            return new TranslationService();
        },

        SeasonalIngredientService::class => function () {
            return new SeasonalIngredientService();
        },

        // Stripe
        StripeClient::class => function () {
            return new StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? '');
        },

        // Logger
        Logger::class => function () {
            $logger = new Logger('prepmeal');
            $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));
            return $logger;
        },

        // Configuration Twig
        Twig::class => function () {
            $twig = Twig::create(__DIR__ . '/../templates', [
                'cache' => __DIR__ . '/../cache/twig',
                'auto_reload' => true,
                'debug' => true
            ]);

            // Ajouter les extensions Twig personnalisées
            $twig->addExtension(new \PrepMeal\Core\Twig\TranslationExtension());

            return $twig;
        }
    ]);
};
