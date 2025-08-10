# PrepMeal - Application de Planification de Repas

PrepMeal est une application web moderne pour la planification de repas personnalisés avec des recettes de saison, des listes de courses automatiques et des fonctionnalités nutritionnelles avancées.

## 🚀 Fonctionnalités

- **Planification de repas intelligente** avec des recettes de saison
- **Génération automatique de listes de courses**
- **Recettes multilingues** (Français, Anglais, Espagnol, Allemand)
- **Filtres par régime alimentaire** (Végétarien, Vegan, Sans gluten, etc.)
- **Gestion des allergènes**
- **Export PDF et iCal**
- **Interface responsive** et moderne
- **Système de favoris**
- **Calcul nutritionnel**

## 📋 Prérequis

- PHP 8.0 ou supérieur
- MySQL 5.7 ou supérieur / MariaDB 10.2 ou supérieur
- Composer
- Serveur web (Apache/Nginx)

## 🛠️ Installation

### 1. Cloner le repository

```bash
git clone https://github.com/votre-username/prepmeal.git
cd prepmeal
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configuration de la base de données

#### Option A : Variables d'environnement (Recommandé)

Créez un fichier `.env` à la racine du projet :

```env
# Configuration de la base de données
DB_HOST=localhost
DB_PORT=3306
DB_NAME=prepmeal
DB_USER=root
DB_PASSWORD=votre_mot_de_passe

# Configuration Stripe (optionnel)
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
```

#### Option B : Fichier de configuration

1. Copiez le fichier d'exemple :
```bash
cp db_config.example.php db_config.php
cp config/container.example.php config/container.php
```

2. Modifiez `db_config.php` avec vos informations de base de données :
```php
$db_config = [
    'host' => 'localhost',
    'dbname' => 'prepmeal',
    'username' => 'root',
    'password' => 'votre_mot_de_passe',
    'port' => 3306,
    'charset' => 'utf8mb4'
];
```

### 4. Créer la base de données

```sql
CREATE DATABASE prepmeal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Initialiser la base de données

```bash
php database/schema.sql
```

Ou utilisez le script d'initialisation automatique :

```bash
php -r "
require_once 'vendor/autoload.php';
require_once 'db_config.php';

try {
    \$pdo = createDatabaseConnection(\$db_config);
    \$sql = file_get_contents('database/schema.sql');
    \$pdo->exec(\$sql);
    echo '✅ Base de données initialisée avec succès\n';
} catch (Exception \$e) {
    echo '❌ Erreur: ' . \$e->getMessage() . '\n';
}
"
```

### 6. Configurer le serveur web

#### Apache

Créez un fichier `.htaccess` dans le dossier `public/` :

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### Nginx

```nginx
server {
    listen 80;
    server_name prepmeal.local;
    root /path/to/prepmeal/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 7. Permissions des dossiers

```bash
chmod -R 755 cache/
chmod -R 755 logs/
chmod -R 755 public/uploads/
```

## 🔧 Configuration

### Variables d'environnement

| Variable | Description | Défaut |
|----------|-------------|---------|
| `DB_HOST` | Hôte de la base de données | `localhost` |
| `DB_PORT` | Port de la base de données | `3306` |
| `DB_NAME` | Nom de la base de données | `prepmeal` |
| `DB_USER` | Utilisateur de la base de données | `root` |
| `DB_PASSWORD` | Mot de passe de la base de données | `` |
| `STRIPE_SECRET_KEY` | Clé secrète Stripe | `` |
| `STRIPE_PUBLISHABLE_KEY` | Clé publique Stripe | `` |

### Configuration Cloudron

Pour le déploiement sur Cloudron, les variables d'environnement suivantes sont automatiquement disponibles :

- `CLOUDRON_MYSQL_HOST`
- `CLOUDRON_MYSQL_PORT`
- `CLOUDRON_MYSQL_DATABASE`
- `CLOUDRON_MYSQL_USERNAME`
- `CLOUDRON_MYSQL_PASSWORD`

## 🚀 Déploiement

### Développement local

```bash
php -S localhost:8000 -t public/
```

### Production

1. Configurez votre serveur web pour pointer vers le dossier `public/`
2. Assurez-vous que les variables d'environnement sont configurées
3. Vérifiez les permissions des dossiers `cache/` et `logs/`

## 📁 Structure du projet

```
prepmeal/
├── config/                 # Configuration de l'application
├── database/              # Schémas et migrations
├── public/                # Point d'entrée web
├── src/                   # Code source
│   ├── Controllers/       # Contrôleurs
│   ├── Core/             # Logique métier
│   │   ├── Database/     # Couche d'accès aux données
│   │   ├── Models/       # Modèles de données
│   │   └── Services/     # Services métier
│   └── Views/            # Vues
├── templates/             # Templates Twig
├── vendor/               # Dépendances Composer
├── .env                  # Variables d'environnement
├── .gitignore           # Fichiers ignorés par Git
└── composer.json        # Dépendances PHP
```

## 🔒 Sécurité

### Fichiers sensibles exclus du repository

- `.env` - Variables d'environnement
- `db_config.php` - Configuration de base de données
- `config/container.php` - Configuration des services
- `logs/` - Fichiers de logs
- `cache/` - Fichiers de cache
- `vendor/` - Dépendances

### Bonnes pratiques

1. **Ne jamais commiter** les fichiers de configuration avec des informations sensibles
2. Utilisez les **variables d'environnement** pour les informations sensibles
3. Configurez correctement les **permissions** des dossiers
4. Utilisez HTTPS en **production**
5. Gardez les **dépendances à jour**

## 🤝 Contribution

1. Fork le projet
2. Créez une branche pour votre fonctionnalité (`git checkout -b feature/AmazingFeature`)
3. Committez vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🆘 Support

Si vous rencontrez des problèmes :

1. Vérifiez les logs dans le dossier `logs/`
2. Consultez la documentation
3. Ouvrez une issue sur GitHub

## 🔄 Mise à jour

```bash
git pull origin main
composer install
```

N'oubliez pas de vérifier les changements dans les fichiers de configuration d'exemple.
