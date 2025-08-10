<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PrepMeal\Core\Database\DatabaseConnection;
use PrepMeal\Core\Database\DatabaseInitializer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Set up logger
$logger = new Logger('prepmeal');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));

header('Content-Type: text/plain; charset=utf-8');

echo "=== Cloudron Database Initialization ===\n\n";

try {
    // Use Cloudron environment variables
    $host = getenv('CLOUDRON_MYSQL_HOST') ?: 'mysql';
    $port = getenv('CLOUDRON_MYSQL_PORT') ?: '3306';
    $database = getenv('CLOUDRON_MYSQL_DATABASE') ?: 'prepmeal';
    $username = getenv('CLOUDRON_MYSQL_USERNAME') ?: 'prepmeal_user';
    $password = getenv('CLOUDRON_MYSQL_PASSWORD') ?: 'your_password_here';
    
    echo "Connecting to Cloudron MySQL: {$host}:{$port}/{$database}\n";
    
    // Combine host and port for the DSN
    $hostWithPort = $host;
    if ($port !== '3306') {
        $hostWithPort = $host . ':' . $port;
    }
    
    $dbConnection = new DatabaseConnection($hostWithPort, $database, $username, $password, $logger);
    echo "✓ Database connection established successfully\n";
    
    $initializer = new DatabaseInitializer($dbConnection, $logger);
    echo "Starting database initialization...\n";
    
    $result = $initializer->initialize();
    
    if ($result) {
        echo "✓ Database initialization completed successfully\n";
        echo "✓ Tables created and sample data inserted\n";
        echo "\nYou can now access your application normally.\n";
    } else {
        echo "✗ Database initialization failed\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== End ===\n";

