-- Création de la base de données
CREATE DATABASE IF NOT EXISTS prepmeal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prepmeal;

-- Table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    locale VARCHAR(5) DEFAULT 'fr',
    units ENUM('metric', 'imperial') DEFAULT 'metric',
    preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des recettes
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
    INDEX idx_difficulty (difficulty),
    INDEX idx_season (season(100))
);

-- Table des ingrédients des recettes
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
);

-- Table des favoris utilisateur
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
);

-- Table des plannings de repas
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
);

-- Table des jours de planning
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
);

-- Table des abonnements
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
);

-- Table des paiements
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
);

-- Table des logs d'activité
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
);

-- Insertion de données d'exemple pour les recettes
INSERT INTO recipes (id, title, description, category, season, prep_time, cook_time, servings, difficulty, nutrition, allergens, dietary_restrictions, instructions, tags) VALUES
(
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
),
(
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
);

-- Insertion des ingrédients pour la recette 001
INSERT INTO recipe_ingredients (recipe_id, name, quantity, seasonal, season) VALUES
(
    'recipe_001',
    '{"fr": "quinoa", "en": "quinoa", "es": "quinoa", "de": "Quinoa"}',
    '{"metric": {"amount": 200, "unit": "g"}, "imperial": {"amount": 7, "unit": "oz"}}',
    FALSE,
    NULL
),
(
    'recipe_001',
    '{"fr": "courgettes", "en": "zucchini", "es": "calabacín", "de": "Zucchini"}',
    '{"metric": {"amount": 2, "unit": "pièces"}, "imperial": {"amount": 2, "unit": "pieces"}}',
    TRUE,
    '["ete", "automne"]'
),
(
    'recipe_001',
    '{"fr": "tomates cerises", "en": "cherry tomatoes", "es": "tomates cherry", "de": "Kirschtomaten"}',
    '{"metric": {"amount": 200, "unit": "g"}, "imperial": {"amount": 7, "unit": "oz"}}',
    TRUE,
    '["ete"]'
);

-- Insertion des ingrédients pour la recette 002
INSERT INTO recipe_ingredients (recipe_id, name, quantity, seasonal, season) VALUES
(
    'recipe_002',
    '{"fr": "potiron", "en": "pumpkin", "es": "calabaza", "de": "Kürbis"}',
    '{"metric": {"amount": 1, "unit": "kg"}, "imperial": {"amount": 2.2, "unit": "lb"}}',
    TRUE,
    '["automne", "hiver"]'
),
(
    'recipe_002',
    '{"fr": "oignon", "en": "onion", "es": "cebolla", "de": "Zwiebel"}',
    '{"metric": {"amount": 1, "unit": "pièce"}, "imperial": {"amount": 1, "unit": "piece"}}',
    FALSE,
    NULL
),
(
    'recipe_002',
    '{"fr": "crème fraîche", "en": "heavy cream", "es": "nata", "de": "Sahne"}',
    '{"metric": {"amount": 200, "unit": "ml"}, "imperial": {"amount": 6.8, "unit": "fl oz"}}',
    FALSE,
    NULL
);
