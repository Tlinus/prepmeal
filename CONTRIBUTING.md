# Guide de Contribution - PrepMeal

Merci de votre intérêt pour contribuer à PrepMeal ! Ce document vous guidera à travers le processus de contribution.

## 🚀 Comment Contribuer

### 1. Fork et Clone

1. Fork le repository sur GitHub
2. Clone votre fork localement :
   ```bash
   git clone https://github.com/votre-username/prepmeal.git
   cd prepmeal
   ```

### 2. Configuration de l'environnement

1. Installez les dépendances :
   ```bash
   composer install
   ```

2. Configurez la base de données :
   ```bash
   cp env.example .env
   cp db_config.example.php db_config.php
   cp config/container.example.php config/container.php
   ```

3. Modifiez les fichiers de configuration avec vos paramètres de base de données

4. Créez la base de données et exécutez les migrations :
   ```bash
   php database/schema.sql
   ```

### 3. Créer une branche

Créez une branche pour votre fonctionnalité :
```bash
git checkout -b feature/nom-de-votre-fonctionnalite
```

### 4. Développement

- Suivez les standards de code (voir ci-dessous)
- Écrivez des tests pour les nouvelles fonctionnalités
- Documentez votre code
- Testez votre code localement

### 5. Commit et Push

```bash
git add .
git commit -m "feat: ajouter une nouvelle fonctionnalité"
git push origin feature/nom-de-votre-fonctionnalite
```

### 6. Pull Request

1. Allez sur GitHub et créez une Pull Request
2. Remplissez le template de Pull Request
3. Attendez la review

## 📋 Standards de Code

### PHP

- Suivez les standards PSR-12
- Utilisez des types stricts (`declare(strict_types=1);`)
- Documentez les fonctions avec PHPDoc
- Utilisez des noms de variables et fonctions descriptifs

```php
<?php

declare(strict_types=1);

/**
 * Calcule le total nutritionnel d'un repas
 *
 * @param array $ingredients Liste des ingrédients
 * @return array Données nutritionnelles
 */
public function calculateNutritionalTotal(array $ingredients): array
{
    // Votre code ici
}
```

### JavaScript

- Utilisez ES6+ features
- Suivez les conventions camelCase
- Documentez les fonctions complexes

```javascript
/**
 * Calcule le total des calories
 * @param {Array} meals - Liste des repas
 * @returns {number} Total des calories
 */
const calculateTotalCalories = (meals) => {
    return meals.reduce((total, meal) => total + meal.calories, 0);
};
```

### CSS/SCSS

- Utilisez des noms de classes BEM
- Organisez le code logiquement
- Commentez les sections importantes

```scss
// Composant de carte de recette
.recipe-card {
    &__header {
        // Styles du header
    }
    
    &__body {
        // Styles du body
    }
    
    &--featured {
        // Variante featured
    }
}
```

## 🧪 Tests

### Tests Unitaires

- Écrivez des tests pour toutes les nouvelles fonctionnalités
- Utilisez PHPUnit pour les tests PHP
- Maintenez une couverture de code élevée

```php
<?php

use PHPUnit\Framework\TestCase;

class RecipeServiceTest extends TestCase
{
    public function testCalculateNutritionalTotal()
    {
        $service = new RecipeService();
        $ingredients = [/* ... */];
        
        $result = $service->calculateNutritionalTotal($ingredients);
        
        $this->assertEquals(500, $result['calories']);
    }
}
```

### Tests d'Intégration

- Testez les endpoints API
- Testez les interactions avec la base de données
- Testez les workflows complets

## 📝 Documentation

### Code

- Documentez les classes et méthodes publiques
- Expliquez la logique complexe
- Ajoutez des exemples d'utilisation

### API

- Documentez tous les endpoints
- Incluez des exemples de requêtes et réponses
- Expliquez les codes d'erreur

### Utilisateur

- Mettez à jour le README si nécessaire
- Documentez les nouvelles fonctionnalités
- Ajoutez des captures d'écran si utile

## 🔒 Sécurité

### Bonnes Pratiques

- Validez toutes les entrées utilisateur
- Utilisez des requêtes préparées pour la base de données
- Échappez les données de sortie
- Ne stockez jamais de mots de passe en clair

### Reporting de Vulnérabilités

Si vous découvrez une vulnérabilité de sécurité :

1. **NE PAS** créer une issue publique
2. Envoyez un email à security@prepmeal.com
3. Incluez tous les détails nécessaires
4. Attendez une réponse avant de divulguer publiquement

## 🎨 Design et UX

### Interface Utilisateur

- Suivez les guidelines de design existantes
- Assurez-vous que l'interface est responsive
- Testez l'accessibilité
- Utilisez les icônes Font Awesome existantes

### Expérience Utilisateur

- Pensez à l'expérience utilisateur
- Testez les workflows complets
- Assurez-vous que les erreurs sont claires
- Optimisez les performances

## 📊 Performance

### Optimisations

- Optimisez les requêtes de base de données
- Utilisez le cache quand c'est approprié
- Minimisez les requêtes HTTP
- Optimisez les images

### Monitoring

- Ajoutez des logs appropriés
- Surveillez les performances
- Testez avec des données volumineuses

## 🔄 Workflow Git

### Messages de Commit

Utilisez le format Conventional Commits :

```
type(scope): description

[optional body]

[optional footer]
```

Types :
- `feat` : Nouvelle fonctionnalité
- `fix` : Correction de bug
- `docs` : Documentation
- `style` : Formatage
- `refactor` : Refactoring
- `test` : Tests
- `chore` : Maintenance

Exemples :
```
feat(meal-planning): ajouter la génération de planning mensuel
fix(auth): corriger la validation des mots de passe
docs(readme): mettre à jour les instructions d'installation
```

### Branches

- `main` : Code de production
- `develop` : Branche de développement
- `feature/*` : Nouvelles fonctionnalités
- `bugfix/*` : Corrections de bugs
- `hotfix/*` : Corrections urgentes

## 🤝 Code Review

### Avant de soumettre

- [ ] Le code suit les standards
- [ ] Les tests passent
- [ ] La documentation est à jour
- [ ] Le code est testé localement
- [ ] Aucune information sensible n'est exposée

### Pendant la review

- Répondez aux commentaires rapidement
- Soyez ouvert aux suggestions
- Expliquez vos choix de design
- Apportez les modifications demandées

## 🎉 Reconnaissance

Tous les contributeurs seront listés dans le fichier `CONTRIBUTORS.md` et mentionnés dans les releases.

## 📞 Support

Si vous avez des questions :

- Ouvrez une issue sur GitHub
- Rejoignez les discussions GitHub
- Contactez l'équipe de maintenance

Merci de contribuer à PrepMeal ! 🌱
