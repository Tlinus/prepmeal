<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\ContainerBuilder;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Charger les variables d'environnement
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Exception $e) {
    // Si le fichier .env n'existe pas, utiliser les valeurs Cloudron par défaut
    $_ENV['APP_DEBUG'] = 'true';
    $_ENV['DEFAULT_LOCALE'] = 'fr';
    
    // Configuration Cloudron MySQL
    $_ENV['DB_HOST'] = getenv('CLOUDRON_MYSQL_HOST') ?: 'mysql';
    $_ENV['DB_PORT'] = getenv('CLOUDRON_MYSQL_PORT') ?: '3306';
    $_ENV['DB_NAME'] = getenv('CLOUDRON_MYSQL_DATABASE') ?: 'prepmeal';
    $_ENV['DB_USER'] = getenv('CLOUDRON_MYSQL_USERNAME') ?: 'prepmeal_user';
    $_ENV['DB_PASS'] = getenv('CLOUDRON_MYSQL_PASSWORD') ?: 'your_password_here';
    $_ENV['CLOUDRON_MYSQL_URL'] = getenv('CLOUDRON_MYSQL_URL') ?: 'mysql://prepmeal_user:your_password_here@mysql/prepmeal';
    
    // Configuration Cloudron Mail
    $_ENV['MAIL_HOST'] = getenv('CLOUDRON_MAIL_SMTP_SERVER') ?: 'mail';
    $_ENV['MAIL_PORT'] = getenv('CLOUDRON_MAIL_SMTP_PORT') ?: '2525';
    $_ENV['MAIL_USERNAME'] = getenv('CLOUDRON_MAIL_SMTP_USERNAME') ?: 'your_email@example.com';
    $_ENV['MAIL_PASSWORD'] = getenv('CLOUDRON_MAIL_SMTP_PASSWORD') ?: 'your_mail_password_here';
    $_ENV['MAIL_FROM'] = getenv('CLOUDRON_MAIL_FROM') ?: 'your_email@example.com';
    
    // Configuration Cloudron Redis
    $_ENV['REDIS_HOST'] = getenv('CLOUDRON_REDIS_HOST') ?: 'redis';
    $_ENV['REDIS_PORT'] = getenv('CLOUDRON_REDIS_PORT') ?: '6379';
    $_ENV['REDIS_PASSWORD'] = getenv('CLOUDRON_REDIS_PASSWORD') ?: 'your_redis_password_here';
}

// Configuration des erreurs
$debugMode = isset($_ENV['APP_DEBUG']) ? 
    (strtolower($_ENV['APP_DEBUG']) === 'true') : 
    false;

if ($debugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
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

// Initialisation de la base de données AVANT la création de l'application
try {
    $initializer = $container->get(\PrepMeal\Core\Database\DatabaseInitializer::class);
    $initializer->initialize();
} catch (\Exception $e) {
    // Log l'erreur mais ne pas arrêter l'application
    error_log('Database initialization failed: ' . $e->getMessage());
}

// Création de l'application Slim
$app = AppFactory::createFromContainer($container);

// Ajout du middleware d'erreur
$debugMode = isset($_ENV['APP_DEBUG']) ? 
    (strtolower($_ENV['APP_DEBUG']) === 'true') : 
    false;

$app->addErrorMiddleware(
    $debugMode,
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
