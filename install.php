<?php

declare(strict_types=1);

/**
 * Script d'installation pour PrepMeal
 * 
 * Ce script configure l'application en :
 * - Vérifiant les prérequis
 * - Créant les dossiers nécessaires
 * - Configurant la base de données
 * - Installant les dépendances
 */

echo "=== Installation de PrepMeal ===\n\n";

// Vérification des prérequis
echo "1. Vérification des prérequis...\n";

$requirements = [
    'php' => '8.1.0',
    'extensions' => ['pdo_mysql', 'json', 'mbstring', 'curl', 'openssl']
];

// Vérifier la version de PHP
if (version_compare(PHP_VERSION, $requirements['php'], '<')) {
    die("❌ PHP " . $requirements['php'] . " ou supérieur requis. Version actuelle : " . PHP_VERSION . "\n");
}
echo "✅ PHP " . PHP_VERSION . " OK\n";

// Vérifier les extensions
foreach ($requirements['extensions'] as $extension) {
    if (!extension_loaded($extension)) {
        die("❌ Extension PHP '$extension' requise mais non installée.\n");
    }
    echo "✅ Extension '$extension' OK\n";
}

// Vérifier Composer
if (!file_exists('composer.json')) {
    die("❌ Fichier composer.json non trouvé. Assurez-vous d'être dans le répertoire racine du projet.\n");
}
echo "✅ Composer OK\n";

echo "\n2. Création des dossiers nécessaires...\n";

$directories = [
    'logs',
    'cache',
    'cache/twig',
    'public/uploads',
    'public/assets/css',
    'public/assets/js',
    'public/assets/images',
    'templates'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Dossier '$dir' créé\n";
        } else {
            echo "❌ Impossible de créer le dossier '$dir'\n";
        }
    } else {
        echo "✅ Dossier '$dir' existe déjà\n";
    }
}

echo "\n3. Installation des dépendances Composer...\n";

if (!file_exists('vendor/autoload.php')) {
    echo "Installation des dépendances...\n";
    system('composer install --no-dev --optimize-autoloader');
} else {
    echo "✅ Dépendances déjà installées\n";
}

echo "\n4. Configuration de l'environnement...\n";

if (!file_exists('.env')) {
    if (copy('env.example', '.env')) {
        echo "✅ Fichier .env créé à partir de env.example\n";
        echo "⚠️  N'oubliez pas de configurer vos variables d'environnement dans .env\n";
    } else {
        echo "❌ Impossible de créer le fichier .env\n";
    }
} else {
    echo "✅ Fichier .env existe déjà\n";
}

echo "\n5. Configuration de la base de données...\n";

// Charger les variables d'environnement
if (file_exists('.env')) {
    $envContent = file_get_contents('.env');
    $envLines = explode("\n", $envContent);
    $env = [];
    
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    
    // Vérifier la connexion à la base de données
    try {
        $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Connexion à la base de données réussie\n";
        
        // Vérifier si les tables existent
        $tables = ['users', 'recipes', 'meal_plans', 'subscriptions'];
        $missingTables = [];
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            echo "⚠️  Tables manquantes : " . implode(', ', $missingTables) . "\n";
            echo "   Exécutez le script SQL dans database/schema.sql\n";
        } else {
            echo "✅ Toutes les tables existent\n";
        }
        
    } catch (PDOException $e) {
        echo "❌ Erreur de connexion à la base de données : " . $e->getMessage() . "\n";
        echo "   Vérifiez vos paramètres dans .env\n";
    }
} else {
    echo "⚠️  Fichier .env non trouvé, impossible de vérifier la base de données\n";
}

echo "\n6. Configuration des permissions...\n";

$writableDirs = ['logs', 'cache', 'public/uploads'];
foreach ($writableDirs as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ Dossier '$dir' accessible en écriture\n";
        } else {
            echo "⚠️  Dossier '$dir' non accessible en écriture\n";
            echo "   Exécutez : chmod 755 $dir\n";
        }
    }
}

echo "\n7. Vérification de la configuration web...\n";

// Vérifier si le fichier .htaccess existe
if (file_exists('public/.htaccess')) {
    echo "✅ Fichier .htaccess présent\n";
} else {
    echo "⚠️  Fichier .htaccess manquant dans public/\n";
}

// Vérifier si le point d'entrée existe
if (file_exists('public/index.php')) {
    echo "✅ Point d'entrée public/index.php présent\n";
} else {
    echo "❌ Point d'entrée public/index.php manquant\n";
}

echo "\n=== Installation terminée ===\n\n";

echo "📋 Prochaines étapes :\n";
echo "1. Configurez vos variables d'environnement dans .env\n";
echo "2. Importez le schéma de base de données : mysql -u root -p < database/schema.sql\n";
echo "3. Configurez votre serveur web pour pointer vers le dossier public/\n";
echo "4. Testez l'application en visitant votre domaine\n\n";

echo "🔧 Configuration recommandée pour Apache :\n";
echo "DocumentRoot: /path/to/prepmeal/public\n";
echo "AllowOverride: All\n\n";

echo "🔧 Configuration recommandée pour Nginx :\n";
echo "root /path/to/prepmeal/public;\n";
echo "try_files \$uri \$uri/ /index.php?\$query_string;\n\n";

echo "🚀 Pour démarrer en mode développement :\n";
echo "php -S localhost:8000 -t public/\n\n";

echo "📚 Documentation : README.md\n";
echo "🐛 Support : https://github.com/votre-username/prepmeal/issues\n\n";
