<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

use PrepMeal\Core\Database\RecipeRepository;
use PrepMeal\Core\Models\Recipe;

class RecipeService
{
    private RecipeRepository $recipeRepository;

    public function __construct(RecipeRepository $recipeRepository)
    {
        $this->recipeRepository = $recipeRepository;
    }

    public function getAllRecipes(array $filters = []): array
    {
        return $this->recipeRepository->findAll($filters);
    }

    public function getRecipeById(string $id): ?Recipe
    {
        return $this->recipeRepository->findById($id);
    }

    public function getPopularRecipes(int $limit = 10): array
    {
        // Pour l'exemple, on retourne des recettes aléatoires
        // En production, vous pourriez implémenter un système de popularité
        return $this->recipeRepository->getRandomRecipes($limit);
    }

    public function getSeasonalRecipes(string $season, int $limit = 10): array
    {
        return $this->recipeRepository->findBySeason([$season]);
    }

    public function getRecipesByDietType(string $dietType): array
    {
        return $this->recipeRepository->findByDietType($dietType);
    }

    public function getUserFavorites(int $userId): array
    {
        return $this->recipeRepository->findFavoritesByUserId($userId);
    }

    public function toggleFavorite(int $userId, string $recipeId): bool
    {
        return $this->recipeRepository->toggleFavorite($userId, $recipeId);
    }

    public function searchRecipes(string $query, array $filters = []): array
    {
        // Implémentation basique de recherche
        // En production, vous pourriez utiliser Elasticsearch ou une recherche full-text
        $allRecipes = $this->recipeRepository->findAll($filters);
        $results = [];

        foreach ($allRecipes as $recipe) {
            $title = strtolower($recipe->getTitle());
            $description = strtolower($recipe->getDescription());
            $query = strtolower($query);

            if (strpos($title, $query) !== false || strpos($description, $query) !== false) {
                $results[] = $recipe;
            }
        }

        return $results;
    }

    public function getRecipesByCategory(string $category): array
    {
        return $this->recipeRepository->findAll(['category' => $category]);
    }

    public function getRecipesByDifficulty(string $difficulty): array
    {
        $allRecipes = $this->recipeRepository->findAll();
        return array_filter($allRecipes, function(Recipe $recipe) use ($difficulty) {
            return $recipe->getDifficulty() === $difficulty;
        });
    }

    public function getQuickRecipes(int $maxTime = 30): array
    {
        return $this->recipeRepository->findAll(['max_prep_time' => $maxTime]);
    }

    public function getHealthyRecipes(): array
    {
        $allRecipes = $this->recipeRepository->findAll();
        return array_filter($allRecipes, function(Recipe $recipe) {
            $nutrition = $recipe->getNutrition();
            // Définir des critères de santé (exemple)
            return ($nutrition['calories'] ?? 0) <= 500 && 
                   ($nutrition['fat'] ?? 0) <= 20 &&
                   ($nutrition['fiber'] ?? 0) >= 5;
        });
    }

    public function getVeganRecipes(): array
    {
        return $this->recipeRepository->findByDietType('vegan');
    }

    public function getVegetarianRecipes(): array
    {
        return $this->recipeRepository->findByDietType('vegetarien');
    }

    public function getGlutenFreeRecipes(): array
    {
        $allRecipes = $this->recipeRepository->findAll();
        return array_filter($allRecipes, function(Recipe $recipe) {
            return $recipe->isGlutenFree();
        });
    }

    public function getRecipesByAllergen(string $allergen): array
    {
        $allRecipes = $this->recipeRepository->findAll();
        return array_filter($allRecipes, function(Recipe $recipe) use ($allergen) {
            return !$recipe->hasAllergen($allergen);
        });
    }

    public function getRecommendedRecipes(int $userId, int $limit = 10): array
    {
        // Algorithme de recommandation basique
        // En production, vous pourriez implémenter un système de ML
        
        // Récupérer les favoris de l'utilisateur
        $favorites = $this->getUserFavorites($userId);
        
        if (empty($favorites)) {
            // Si pas de favoris, retourner des recettes populaires
            return $this->getPopularRecipes($limit);
        }

        // Analyser les préférences basées sur les favoris
        $preferredCategories = [];
        $preferredDietTypes = [];

        foreach ($favorites as $recipe) {
            $category = $recipe->getCategory();
            $preferredCategories[$category] = ($preferredCategories[$category] ?? 0) + 1;

            foreach ($recipe->getDietaryRestrictions() as $dietType) {
                $preferredDietTypes[$dietType] = ($preferredDietTypes[$dietType] ?? 0) + 1;
            }
        }

        // Trouver la catégorie et le régime préférés
        $topCategory = array_keys($preferredCategories, max($preferredCategories))[0] ?? null;
        $topDietType = array_keys($preferredDietTypes, max($preferredDietTypes))[0] ?? null;

        // Récupérer des recettes similaires
        $recommendations = [];
        
        if ($topCategory) {
            $categoryRecipes = $this->getRecipesByCategory($topCategory);
            $recommendations = array_merge($recommendations, $categoryRecipes);
        }

        if ($topDietType) {
            $dietRecipes = $this->getRecipesByDietType($topDietType);
            $recommendations = array_merge($recommendations, $dietRecipes);
        }

        // Supprimer les doublons et les recettes déjà favorites
        $favoriteIds = array_map(fn($recipe) => $recipe->getId(), $favorites);
        $recommendations = array_filter($recommendations, function(Recipe $recipe) use ($favoriteIds) {
            return !in_array($recipe->getId(), $favoriteIds);
        });

        // Retourner les premières recommandations
        return array_slice(array_values($recommendations), 0, $limit);
    }

    public function getNutritionalStats(array $recipes): array
    {
        $stats = [
            'total_calories' => 0,
            'total_protein' => 0,
            'total_carbs' => 0,
            'total_fat' => 0,
            'total_fiber' => 0,
            'recipe_count' => count($recipes)
        ];

        foreach ($recipes as $recipe) {
            $nutrition = $recipe->getNutrition();
            $stats['total_calories'] += $nutrition['calories'] ?? 0;
            $stats['total_protein'] += $nutrition['protein'] ?? 0;
            $stats['total_carbs'] += $nutrition['carbs'] ?? 0;
            $stats['total_fat'] += $nutrition['fat'] ?? 0;
            $stats['total_fiber'] += $nutrition['fiber'] ?? 0;
        }

        if ($stats['recipe_count'] > 0) {
            $stats['avg_calories'] = round($stats['total_calories'] / $stats['recipe_count']);
            $stats['avg_protein'] = round($stats['total_protein'] / $stats['recipe_count'], 1);
            $stats['avg_carbs'] = round($stats['total_carbs'] / $stats['recipe_count'], 1);
            $stats['avg_fat'] = round($stats['total_fat'] / $stats['recipe_count'], 1);
            $stats['avg_fiber'] = round($stats['total_fiber'] / $stats['recipe_count'], 1);
        }

        return $stats;
    }

    public function getRecipeCategories(): array
    {
        $allRecipes = $this->recipeRepository->findAll();
        $categories = [];

        foreach ($allRecipes as $recipe) {
            $category = $recipe->getCategory();
            if (!isset($categories[$category])) {
                $categories[$category] = 0;
            }
            $categories[$category]++;
        }

        return $categories;
    }

    public function getRecipeDifficulties(): array
    {
        $allRecipes = $this->recipeRepository->findAll();
        $difficulties = [];

        foreach ($allRecipes as $recipe) {
            $difficulty = $recipe->getDifficulty();
            if (!isset($difficulties[$difficulty])) {
                $difficulties[$difficulty] = 0;
            }
            $difficulties[$difficulty]++;
        }

        return $difficulties;
    }
}
