<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use PrepMeal\Core\Database\DatabaseConnection;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Set up logger
$logger = new Logger('prepmeal');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/app.log', Logger::DEBUG));

echo "=== Fix Database Tables ===\n";

try {
    // Use Cloudron environment variables
    $host = getenv('CLOUDRON_MYSQL_HOST') ?: 'mysql';
    $port = getenv('CLOUDRON_MYSQL_PORT') ?: '3306';
    $database = getenv('CLOUDRON_MYSQL_DATABASE') ?: 'prepmeal';
    $username = getenv('CLOUDRON_MYSQL_USERNAME') ?: 'prepmeal_user';
    $password = getenv('CLOUDRON_MYSQL_PASSWORD') ?: 'your_password_here';
    
    // Combine host and port for the DSN
    $hostWithPort = $host;
    if ($port !== '3306') {
        $hostWithPort = $host . ':' . $port;
    }
    
    $dbConnection = new DatabaseConnection($hostWithPort, $database, $username, $password, $logger);
    echo "✓ Database connection established successfully\n";
    
    $pdo = $dbConnection->getConnection();
    
    // Check which tables exist
    $requiredTables = ['users', 'recipes', 'recipe_ingredients', 'user_favorites', 'meal_plans', 'meal_plan_days', 'subscriptions', 'payments', 'activity_logs'];
    $existingTables = [];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE '" . $table . "'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
            echo "✓ Table '{$table}' exists\n";
        } else {
            $missingTables[] = $table;
            echo "✗ Table '{$table}' missing\n";
        }
    }
    
    if (empty($missingTables)) {
        echo "\n✓ All tables exist! Database is ready.\n";
        return;
    }
    
    echo "\nCreating missing tables...\n";
    
    // Create only the missing tables
    if (in_array('recipes', $missingTables)) {
        echo "Creating recipes table...\n";
                 $pdo->exec("
             CREATE TABLE recipes (
                 id VARCHAR(50) PRIMARY KEY,
                 title JSON NOT NULL,
                 description JSON NOT NULL,
                 category VARCHAR(50) NOT NULL,
                 season JSON,
                 prep_time INT NOT NULL DEFAULT 0,
                 cook_time INT NOT NULL DEFAULT 0,
                 servings INT NOT NULL DEFAULT 2,
                 difficulty ENUM('facile', 'moyen', 'difficile') DEFAULT 'facile',
                 nutrition JSON,
                 allergens JSON,
                 dietary_restrictions JSON,
                 instructions JSON NOT NULL,
                 tags JSON,
                 image_url VARCHAR(255),
                 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                 INDEX idx_category (category),
                 INDEX idx_difficulty (difficulty)
             )
         ");
        echo "✓ recipes table created\n";
    }
    
    if (in_array('recipe_ingredients', $missingTables)) {
        echo "Creating recipe_ingredients table...\n";
        $pdo->exec("
            CREATE TABLE recipe_ingredients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipe_id VARCHAR(50) NOT NULL,
                name JSON NOT NULL,
                quantity JSON NOT NULL,
                seasonal BOOLEAN DEFAULT FALSE,
                season JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
                INDEX idx_recipe_id (recipe_id)
            )
        ");
        echo "✓ recipe_ingredients table created\n";
    }
    
    if (in_array('user_favorites', $missingTables)) {
        echo "Creating user_favorites table...\n";
        $pdo->exec("
            CREATE TABLE user_favorites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                recipe_id VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_recipe (user_id, recipe_id),
                INDEX idx_user_id (user_id),
                INDEX idx_recipe_id (recipe_id)
            )
        ");
        echo "✓ user_favorites table created\n";
    }
    
    if (in_array('meal_plans', $missingTables)) {
        echo "Creating meal_plans table...\n";
        $pdo->exec("
            CREATE TABLE meal_plans (
                id VARCHAR(50) PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255),
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                preferences JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_dates (start_date, end_date)
            )
        ");
        echo "✓ meal_plans table created\n";
    }
    
    if (in_array('meal_plan_days', $missingTables)) {
        echo "Creating meal_plan_days table...\n";
        $pdo->exec("
            CREATE TABLE meal_plan_days (
                id INT AUTO_INCREMENT PRIMARY KEY,
                plan_id VARCHAR(50) NOT NULL,
                date DATE NOT NULL,
                meals JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (plan_id) REFERENCES meal_plans(id) ON DELETE CASCADE,
                UNIQUE KEY unique_plan_date (plan_id, date),
                INDEX idx_plan_id (plan_id),
                INDEX idx_date (date)
            )
        ");
        echo "✓ meal_plan_days table created\n";
    }
    
    if (in_array('subscriptions', $missingTables)) {
        echo "Creating subscriptions table...\n";
        $pdo->exec("
            CREATE TABLE subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                stripe_subscription_id VARCHAR(255) UNIQUE,
                plan_type ENUM('free', 'monthly', 'yearly') DEFAULT 'free',
                status ENUM('active', 'canceled', 'past_due', 'unpaid') DEFAULT 'active',
                current_period_start TIMESTAMP NULL,
                current_period_end TIMESTAMP NULL,
                canceled_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_stripe_id (stripe_subscription_id)
            )
        ");
        echo "✓ subscriptions table created\n";
    }
    
    if (in_array('payments', $missingTables)) {
        echo "Creating payments table...\n";
        $pdo->exec("
            CREATE TABLE payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                subscription_id INT,
                stripe_payment_intent_id VARCHAR(255) UNIQUE,
                amount INT NOT NULL,
                currency VARCHAR(3) DEFAULT 'eur',
                status ENUM('pending', 'succeeded', 'failed', 'canceled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_stripe_id (stripe_payment_intent_id)
            )
        ");
        echo "✓ payments table created\n";
    }
    
    if (in_array('activity_logs', $missingTables)) {
        echo "Creating activity_logs table...\n";
        $pdo->exec("
            CREATE TABLE activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(100) NOT NULL,
                details JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            )
        ");
        echo "✓ activity_logs table created\n";
    }
    
    // Insert sample data if recipes table was just created
    if (in_array('recipes', $missingTables)) {
        echo "\nInserting sample recipes...\n";
        
        // Sample recipe 1
        $stmt = $pdo->prepare("
            INSERT INTO recipes (id, title, description, category, season, prep_time, cook_time, servings, difficulty, nutrition, allergens, dietary_restrictions, instructions, tags) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            'recipe_001',
            '{"fr": "Salade de quinoa aux légumes de saison", "en": "Seasonal vegetable quinoa salad", "es": "Ensalada de quinoa con verduras de temporada", "de": "Quinoa-Salat mit Saisongemüse"}',
            '{"fr": "Une salade nutritive et colorée parfaite pour l\'été", "en": "A nutritious and colorful salad perfect for summer", "es": "Una ensalada nutritiva y colorida perfecta para el verano", "de": "Ein nahrhafter und farbenfroher Salat, perfekt für den Sommer"}',
            'equilibre',
            '["ete", "automne"]',
            15,
            20,
            4,
            'facile',
            '{"calories": 320, "protein": 12, "carbs": 45, "fat": 8, "fiber": 6}',
            '["gluten"]',
            '["vegetarien", "vegan"]',
            '[{"step": 1, "text": {"fr": "Rincer le quinoa et le faire cuire dans 400ml d\'eau salée pendant 15 minutes", "en": "Rinse quinoa and cook in 400ml salted water for 15 minutes", "es": "Enjuagar la quinoa y cocinar en 400ml de agua con sal durante 15 minutos", "de": "Quinoa spülen und in 400ml Salzwasser 15 Minuten kochen"}}, {"step": 2, "text": {"fr": "Pendant ce temps, couper les légumes en petits morceaux", "en": "Meanwhile, cut vegetables into small pieces", "es": "Mientras tanto, cortar las verduras en trozos pequeños", "de": "Währenddessen Gemüse in kleine Stücke schneiden"}}]',
            '["leger", "rapide", "sain"]'
        ]);
        
        // Sample recipe 2
        $stmt->execute([
            'recipe_002',
            '{"fr": "Soupe de potiron à la crème", "en": "Creamy pumpkin soup", "es": "Sopa de calabaza con crema", "de": "Kürbissuppe mit Sahne"}',
            '{"fr": "Une soupe réconfortante parfaite pour l\'automne", "en": "A comforting soup perfect for autumn", "es": "Una sopa reconfortante perfecta para el otoño", "de": "Eine wärmende Suppe, perfekt für den Herbst"}',
            'plat_principal',
            '["automne", "hiver"]',
            10,
            30,
            6,
            'facile',
            '{"calories": 280, "protein": 8, "carbs": 35, "fat": 12, "fiber": 8}',
            '["lactose"]',
            '["vegetarien"]',
            '[{"step": 1, "text": {"fr": "Éplucher et couper le potiron en cubes", "en": "Peel and cut pumpkin into cubes", "es": "Pelar y cortar la calabaza en cubos", "de": "Kürbis schälen und in Würfel schneiden"}}, {"step": 2, "text": {"fr": "Faire revenir l\'oignon dans l\'huile d\'olive", "en": "Sauté onion in olive oil", "es": "Sofreír la cebolla en aceite de oliva", "de": "Zwiebel in Olivenöl anbraten"}}]',
            '["reconfortant", "saison", "cremeux"]'
        ]);
        
        echo "✓ Sample recipes inserted\n";
        
        // Insert ingredients for recipe 1
        $stmt = $pdo->prepare("
            INSERT INTO recipe_ingredients (recipe_id, name, quantity, seasonal, season) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $ingredients1 = [
            ['quinoa', '{"metric": {"amount": 200, "unit": "g"}, "imperial": {"amount": 7, "unit": "oz"}}', false, null],
            ['courgettes', '{"metric": {"amount": 2, "unit": "pièces"}, "imperial": {"amount": 2, "unit": "pieces"}}', true, '["ete", "automne"]'],
            ['tomates cerises', '{"metric": {"amount": 200, "unit": "g"}, "imperial": {"amount": 7, "unit": "oz"}}', true, '["ete"]']
        ];
        
        foreach ($ingredients1 as $ingredient) {
            $name = '{"fr": "' . $ingredient[0] . '", "en": "' . $ingredient[0] . '", "es": "' . $ingredient[0] . '", "de": "' . $ingredient[0] . '"}';
            $stmt->execute([
                'recipe_001',
                $name,
                $ingredient[1],
                $ingredient[2] ? 1 : 0,
                $ingredient[3]
            ]);
        }
        
        // Insert ingredients for recipe 2
        $ingredients2 = [
            ['potiron', '{"metric": {"amount": 1, "unit": "kg"}, "imperial": {"amount": 2.2, "unit": "lb"}}', true, '["automne", "hiver"]'],
            ['oignon', '{"metric": {"amount": 1, "unit": "pièce"}, "imperial": {"amount": 1, "unit": "piece"}}', false, null],
            ['crème fraîche', '{"metric": {"amount": 200, "unit": "ml"}, "imperial": {"amount": 6.8, "unit": "fl oz"}}', false, null]
        ];
        
        foreach ($ingredients2 as $ingredient) {
            $name = '{"fr": "' . $ingredient[0] . '", "en": "' . $ingredient[0] . '", "es": "' . $ingredient[0] . '", "de": "' . $ingredient[0] . '"}';
            $stmt->execute([
                'recipe_002',
                $name,
                $ingredient[1],
                $ingredient[2] ? 1 : 0,
                $ingredient[3]
            ]);
        }
        
        echo "✓ Sample ingredients inserted\n";
    }
    
    echo "\n✓ Database setup completed successfully!\n";
    echo "✓ Your application should now work properly.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== End ===\n";
