<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\ContainerBuilder;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configuration des erreurs
if ($_ENV['APP_DEBUG'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Configuration du fuseau horaire
date_default_timezone_set('Europe/Paris');

// Configuration des sessions
session_start();

// Configuration de la locale par défaut
if (!isset($_SESSION['locale'])) {
    $_SESSION['locale'] = $_ENV['DEFAULT_LOCALE'] ?? 'fr';
}

// Création du conteneur DI
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

// Création de l'application Slim
$app = AppFactory::createFromContainer($container);

// Ajout du middleware d'erreur
$app->addErrorMiddleware(
    $_ENV['APP_DEBUG'] ?? false,
    true,
    true
);

// Ajout du middleware de routage
$app->addRoutingMiddleware();

// Configuration des routes
$routes = require __DIR__ . '/../config/routes.php';
$routes($app);

// Exécution de l'application
$app->run();
