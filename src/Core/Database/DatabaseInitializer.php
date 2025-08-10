<?php

declare(strict_types=1);

namespace PrepMeal\Core\Database;

use PDO;
use PDOException;
use Monolog\Logger;

class DatabaseInitializer
{
    private DatabaseConnection $dbConnection;
    private Logger $logger;

    public function __construct(DatabaseConnection $dbConnection, Logger $logger)
    {
        $this->dbConnection = $dbConnection;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info('Starting database initialization');
            
            // Check if tables exist
            if ($this->tablesExist()) {
                $this->logger->info('Database tables already exist, skipping initialization');
                return true;
            }

            // Create tables
            $this->createTables();
            
            // Insert sample data
            $this->insertSampleData();
            
            $this->logger->info('Database initialization completed successfully');
            return true;
            
        } catch (PDOException $e) {
            $this->logger->error('Database initialization failed: ' . $e->getMessage());
            return false;
        }
    }

    private function tablesExist(): bool
    {
        $pdo = $this->dbConnection->getConnection();
        
        $sql = "SHOW TABLES LIKE 'recipes'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    private function createTables(): void
    {
        $pdo = $this->dbConnection->getConnection();
        
        // Read and execute schema
        $schemaPath = __DIR__ . '/../../../database/schema.sql';
        if (!file_exists($schemaPath)) {
            throw new \Exception('Schema file not found: ' . $schemaPath);
        }
        
        $schema = file_get_contents($schemaPath);
        
        // Remove CREATE DATABASE and USE statements for Cloudron
        $schema = preg_replace('/CREATE DATABASE.*?;/s', '', $schema);
        $schema = preg_replace('/USE.*?;/s', '', $schema);
        
        // Split into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            function($stmt) { return !empty($stmt); }
        );
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $this->logger->info('Executing SQL: ' . substr($statement, 0, 50) . '...');
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // If table already exists, continue
                    if ($e->getCode() === '42S01') {
                        $this->logger->info('Table already exists, skipping: ' . $e->getMessage());
                        continue;
                    }
                    throw $e;
                }
            }
        }
        
        $this->logger->info('Database tables created successfully');
    }

    private function insertSampleData(): void
    {
        $pdo = $this->dbConnection->getConnection();
        
        // Check if sample data already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $this->logger->info('Sample data already exists, skipping insertion');
            return;
        }
        
        // Insert sample recipes
        $this->insertSampleRecipes($pdo);
        
        $this->logger->info('Sample data inserted successfully');
    }

    private function insertSampleRecipes(PDO $pdo): void
    {
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
        
        // Insert ingredients for recipe 1
        $this->insertRecipeIngredients($pdo, 'recipe_001', [
            ['quinoa', '{"metric": {"amount": 200, "unit": "g"}, "imperial": {"amount": 7, "unit": "oz"}}', false, null],
            ['courgettes', '{"metric": {"amount": 2, "unit": "pièces"}, "imperial": {"amount": 2, "unit": "pieces"}}', true, '["ete", "automne"]'],
            ['tomates cerises', '{"metric": {"amount": 200, "unit": "g"}, "imperial": {"amount": 7, "unit": "oz"}}', true, '["ete"]']
        ]);
        
        // Insert ingredients for recipe 2
        $this->insertRecipeIngredients($pdo, 'recipe_002', [
            ['potiron', '{"metric": {"amount": 1, "unit": "kg"}, "imperial": {"amount": 2.2, "unit": "lb"}}', true, '["automne", "hiver"]'],
            ['oignon', '{"metric": {"amount": 1, "unit": "pièce"}, "imperial": {"amount": 1, "unit": "piece"}}', false, null],
            ['crème fraîche', '{"metric": {"amount": 200, "unit": "ml"}, "imperial": {"amount": 6.8, "unit": "fl oz"}}', false, null]
        ]);
    }

    private function insertRecipeIngredients(PDO $pdo, string $recipeId, array $ingredients): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO recipe_ingredients (recipe_id, name, quantity, seasonal, season) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($ingredients as $ingredient) {
            $name = '{"fr": "' . $ingredient[0] . '", "en": "' . $ingredient[0] . '", "es": "' . $ingredient[0] . '", "de": "' . $ingredient[0] . '"}';
            $stmt->execute([
                $recipeId,
                $name,
                $ingredient[1],
                $ingredient[2] ? 1 : 0,
                $ingredient[3]
            ]);
        }
    }
}
