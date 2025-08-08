# PrepMeal - Planification de Repas Intelligente

Une application web complète de planification de repas permettant de générer des menus personnalisés pour une semaine, un mois ou une année entière, avec prise en compte des fruits et légumes de saison.

## 🚀 Fonctionnalités

### ✨ Fonctionnalités principales
- **Planification intelligente** : Génération de menus personnalisés selon vos préférences
- **Ingrédients de saison** : Prise en compte automatique des fruits et légumes de saison
- **Gestion des allergènes** : Filtrage par allergènes (gluten, lactose, fruits à coque, etc.)
- **Régimes alimentaires** : Support de multiples régimes (vegan, végétarien, cétogène, etc.)
- **Internationalisation** : Support multilingue (FR, EN, ES, DE)
- **Système d'unités** : Conversion automatique métrique/impérial
- **Abonnement Stripe** : Plans gratuits et premium
- **Export/Import** : PDF, calendrier, liste de courses

### 🍽️ Types de régimes supportés
- **Prise de masse** : Surplus calorique, riche en protéines
- **Équilibré** : Répartition nutritionnelle standard
- **Sèche** : Déficit calorique contrôlé
- **Anti-cholestérol** : Faible en graisses saturées
- **Vegan** : 100% végétal
- **Végétarien** : Sans viande ni poisson
- **Recettes simples** : Maximum 5 ingrédients, 30min de préparation
- **Autres** : Cétogène, paléo, sans gluten, méditerranéen

## 🛠️ Technologies utilisées

### Backend
- **PHP 8.1+** avec Slim Framework
- **MySQL** pour la base de données
- **Stripe** pour les paiements
- **Twig** pour les templates
- **Composer** pour la gestion des dépendances

### Frontend
- **HTML5/CSS3** avec Tailwind CSS
- **JavaScript** vanilla avec modules ES6
- **Responsive Design** mobile-first
- **PWA** (Progressive Web App)

## 📋 Prérequis

- PHP 8.1 ou supérieur
- MySQL 8.0 ou supérieur
- Composer
- Serveur web (Apache/Nginx)
- Extension PHP : `pdo_mysql`, `json`, `mbstring`

## 🚀 Installation

### 1. Cloner le projet
```bash
git clone https://github.com/votre-username/prepmeal.git
cd prepmeal
```

### 2. Installer les dépendances
```bash
composer install
```

### 3. Configuration de l'environnement
```bash
# Copier le fichier d'exemple
cp env.example .env

# Éditer le fichier .env avec vos paramètres
nano .env
```

### 4. Configuration de la base de données
```bash
# Créer la base de données
mysql -u root -p < database/schema.sql

# Ou importer le schéma via phpMyAdmin
```

### 5. Configuration du serveur web

#### Apache
Créer un fichier `.htaccess` dans le dossier `public/` :
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
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
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Permissions des dossiers
```bash
# Créer les dossiers nécessaires
mkdir -p logs cache public/uploads

# Donner les permissions appropriées
chmod 755 logs cache public/uploads
chmod 644 .env
```

### 7. Configuration Stripe (optionnel)
Pour les fonctionnalités d'abonnement :
1. Créer un compte Stripe
2. Récupérer vos clés API dans le dashboard Stripe
3. Les ajouter dans le fichier `.env`

## 🎯 Utilisation

### Démarrage rapide
```bash
# Démarrer le serveur de développement PHP
php -S localhost:8000 -t public/

# Ouvrir votre navigateur
open http://localhost:8000
```

### Comptes de test
- **Email** : `demo@example.com`
- **Mot de passe** : `password`

## 📁 Structure du projet

```
prepmeal/
├── config/                 # Configuration de l'application
│   ├── container.php      # Configuration DI
│   └── routes.php         # Définition des routes
├── database/              # Scripts de base de données
│   └── schema.sql        # Schéma de la base de données
├── locales/              # Fichiers de traduction
│   ├── fr.json          # Traductions françaises
│   ├── en.json          # Traductions anglaises
│   ├── es.json          # Traductions espagnoles
│   └── de.json          # Traductions allemandes
├── logs/                 # Fichiers de logs
├── public/               # Dossier public (DocumentRoot)
│   ├── index.php        # Point d'entrée
│   ├── assets/          # CSS, JS, images
│   └── uploads/         # Fichiers uploadés
├── src/                  # Code source
│   ├── Controllers/     # Contrôleurs
│   ├── Core/           # Services et modèles
│   │   ├── Database/   # Couche d'accès aux données
│   │   ├── Models/     # Modèles de données
│   │   └── Services/   # Services métier
│   └── Middleware/     # Middleware
├── templates/           # Templates Twig
├── tests/              # Tests unitaires
├── vendor/             # Dépendances Composer
├── .env                # Variables d'environnement
├── composer.json       # Dépendances PHP
└── README.md          # Ce fichier
```

## 🔧 Configuration

### Variables d'environnement importantes

```env
# Base de données
DB_HOST=localhost
DB_NAME=prepmeal
DB_USER=root
DB_PASS=

# Stripe (optionnel)
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Internationalisation
DEFAULT_LOCALE=fr
SUPPORTED_LOCALES=fr,en,es,de
```

## 🧪 Tests

```bash
# Lancer les tests unitaires
composer test

# Vérifier le code style
composer cs

# Analyse statique
composer stan
```

## 📊 Fonctionnalités avancées

### API REST
L'application expose une API REST pour l'intégration avec d'autres applications :

```bash
# Récupérer toutes les recettes
GET /api/recipes

# Récupérer une recette spécifique
GET /api/recipes/{id}

# Générer un planning
POST /api/generate-plan
```

### Webhooks Stripe
Pour gérer les abonnements automatiquement :
```bash
# Endpoint webhook
POST /webhooks/stripe
```

### Export de données
- **PDF** : Planning de repas en PDF
- **iCal** : Export vers calendrier externe
- **CSV** : Liste de courses

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🆘 Support

- **Documentation** : [Wiki du projet](https://github.com/votre-username/prepmeal/wiki)
- **Issues** : [GitHub Issues](https://github.com/votre-username/prepmeal/issues)
- **Email** : support@prepmeal.com

## 🚀 Roadmap

### Version 1.1
- [ ] Application mobile React Native
- [ ] Intégration avec les applications de fitness
- [ ] Système de recommandations IA

### Version 1.2
- [ ] Mode hors-ligne
- [ ] Synchronisation multi-appareils
- [ ] Partage de plannings entre utilisateurs

### Version 2.0
- [ ] Assistant vocal
- [ ] Reconnaissance d'images d'ingrédients
- [ ] Intégration avec les supermarchés en ligne

---

**PrepMeal** - Planifiez vos repas en toute simplicité ! 🍽️✨
