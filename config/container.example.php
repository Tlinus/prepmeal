<?php

declare(strict_types=1);

use PrepMeal\Core\Database\DatabaseConnection;
use PrepMeal\Core\Database\RecipeRepository;
use PrepMeal\Core\Database\UserRepository;
use PrepMeal\Core\Database\SubscriptionRepository;
use PrepMeal\Core\Database\MealPlanRepository;
use PrepMeal\Core\Database\DatabaseInitializer;
use PrepMeal\Core\Services\RecipeService;
use PrepMeal\Core\Services\MealPlanningService;
use PrepMeal\Core\Services\StripeSubscriptionService;
use PrepMeal\Core\Services\TranslationService;
use PrepMeal\Core\Services\SeasonalIngredientService;
use PrepMeal\Core\Services\UnitConversionService;
use PrepMeal\Core\Services\PdfExportService;
use PrepMeal\Core\Services\UserService;
use PrepMeal\Core\Services\CsrfService;
use PrepMeal\Controllers\IngredientController;
use PrepMeal\Controllers\HomeController;
use PrepMeal\Controllers\RecipeController;
use PrepMeal\Controllers\MealPlanningController;
use PrepMeal\Controllers\SubscriptionController;
use PrepMeal\Controllers\ApiController;
use PrepMeal\Controllers\ExportController;
use PrepMeal\Controllers\AuthController;
use PrepMeal\Controllers\ProfileController;
use PrepMeal\Controllers\SettingsController;
use PrepMeal\Controllers\ErrorController;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Stripe\StripeClient;
use Stripe\Stripe;

return [
    // Logger (no dependencies)
    Logger::class => function () {
        $logger = new Logger('prepmeal');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));
        return $logger;
    },

    // Configuration Twig (no dependencies)
    \Twig\Environment::class => function () {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
        $twig = new \Twig\Environment($loader, [
            'cache' => __DIR__ . '/../cache/twig',
            'auto_reload' => true,
            'debug' => true
        ]);
        return $twig;
    },

    \PrepMeal\Core\Views\TwigView::class => function () {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
        $twig = new \Twig\Environment($loader, [
            'cache' => __DIR__ . '/../cache/twig',
            'auto_reload' => true,
            'debug' => true
        ]);
        return new \PrepMeal\Core\Views\TwigView($twig);
    },

    // Configuration de la base de données
    DatabaseConnection::class => function (Logger $logger) {
        // Use environment variables for database configuration
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_NAME') ?: 'prepmeal';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        
        $logger->info('Using database credentials from environment variables', [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username
        ]);
        
        // Combine host and port for the DSN
        $hostWithPort = $host;
        if ($port !== '3306') {
            $hostWithPort = $host . ':' . $port;
        }
        
        return new DatabaseConnection(
            $hostWithPort,
            $database,
            $username,
            $password,
            $logger
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

    MealPlanRepository::class => function (DatabaseConnection $db) {
        return new MealPlanRepository($db);
    },

    DatabaseInitializer::class => function (DatabaseConnection $db, Logger $logger) {
        return new DatabaseInitializer($db, $logger);
    },

    // Services
    RecipeService::class => function (RecipeRepository $recipeRepo) {
        return new RecipeService($recipeRepo);
    },

    SeasonalIngredientService::class => function () {
        return new SeasonalIngredientService();
    },

    UnitConversionService::class => function () {
        return new UnitConversionService();
    },

    MealPlanningService::class => function (
        RecipeRepository $recipeRepo,
        SeasonalIngredientService $seasonalService,
        UnitConversionService $unitService
    ) {
        return new MealPlanningService($recipeRepo, $seasonalService, $unitService);
    },

    StripeSubscriptionService::class => function (DatabaseConnection $db) {
        $stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: '';
        $stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
        return new StripeSubscriptionService($db, $stripeSecretKey, $stripePublishableKey);
    },

    TranslationService::class => function () {
        return new TranslationService();
    },

    UserService::class => function (UserRepository $userRepository) {
        return new UserService($userRepository);
    },

    CsrfService::class => function () {
        return new CsrfService();
    },

    PdfExportService::class => function (TranslationService $translationService, UnitConversionService $unitService) {
        return new PdfExportService($translationService, $unitService);
    },

    // Controllers
    HomeController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translation,
        RecipeService $recipeService,
        SeasonalIngredientService $seasonalService
    ) {
        return new HomeController($view, $translation, $recipeService, $seasonalService);
    },

    IngredientController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        SeasonalIngredientService $seasonalService
    ) {
        return new IngredientController($view, $translationService, $seasonalService);
    },

    RecipeController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        RecipeService $recipeService,
        RecipeRepository $recipeRepository
    ) {
        return new RecipeController($view, $translationService, $recipeService, $recipeRepository);
    },

    MealPlanningController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        MealPlanningService $mealPlanningService,
        SeasonalIngredientService $seasonalService
    ) {
        return new MealPlanningController($view, $translationService, $mealPlanningService, $seasonalService);
    },

    SubscriptionController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        StripeSubscriptionService $stripeService
    ) {
        return new SubscriptionController($view, $translationService, $stripeService);
    },

    ApiController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        RecipeService $recipeService,
        MealPlanningService $mealPlanningService,
        SeasonalIngredientService $seasonalService,
        TranslationService $translationService,
        UnitConversionService $unitConversionService
    ) {
        return new ApiController($view, $recipeService, $mealPlanningService, $seasonalService, $translationService, $unitConversionService);
    },

    ExportController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        PdfExportService $pdfService,
        MealPlanningService $mealPlanningService,
        StripeSubscriptionService $stripeService
    ) {
        return new ExportController($view, $translationService, $pdfService, $mealPlanningService, $stripeService);
    },

    AuthController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        UserService $userService,
        CsrfService $csrfService
    ) {
        return new AuthController($view, $translationService, $userService, $csrfService);
    },

    ProfileController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService
    ) {
        return new ProfileController($view, $translationService);
    },

    SettingsController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService
    ) {
        return new SettingsController($view, $translationService);
    },

    ErrorController::class => function (
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService
    ) {
        return new ErrorController($view, $translationService);
    },

    // Stripe
    StripeClient::class => function () {
        return new StripeClient(getenv('STRIPE_SECRET_KEY') ?: '');
    }
];
