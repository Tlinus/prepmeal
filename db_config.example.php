<?php
// Configuration de la base de données - FICHIER D'EXEMPLE
// Copiez ce fichier vers db_config.php et modifiez les valeurs selon votre environnement

// Configuration par défaut pour le développement local
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'dbname' => getenv('DB_NAME') ?: 'prepmeal',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'port' => getenv('DB_PORT') ?: 3306,
    'charset' => 'utf8mb4'
];

// Configuration pour Cloudron/Docker (variables d'environnement Cloudron)
if (getenv('CLOUDRON_MYSQL_HOST')) {
    $db_config = [
        'host' => getenv('CLOUDRON_MYSQL_HOST'),
        'dbname' => getenv('CLOUDRON_MYSQL_DATABASE'),
        'username' => getenv('CLOUDRON_MYSQL_USERNAME'),
        'password' => getenv('CLOUDRON_MYSQL_PASSWORD'),
        'port' => getenv('CLOUDRON_MYSQL_PORT') ?: 3306,
        'charset' => 'utf8mb4'
    ];
}

// Fonction pour créer la connexion PDO
function createDatabaseConnection($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
    }
}

// Fonction pour tester la connexion
function testDatabaseConnection($config) {
    try {
        $pdo = createDatabaseConnection($config);
        echo "✅ Connexion à la base de données réussie sur {$config['host']}:{$config['port']}\n";
        return $pdo;
    } catch (Exception $e) {
        echo "❌ " . $e->getMessage() . "\n";
        return false;
    }
}
?>
