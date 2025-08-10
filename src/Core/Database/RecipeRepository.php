<?php

declare(strict_types=1);

namespace PrepMeal\Core\Database;

use PDO;
use PrepMeal\Core\Models\Recipe;
use PrepMeal\Core\Models\Ingredient;

class RecipeRepository
{
    private DatabaseConnection $db;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findAll(array $filters = []): array
    {
        $sql = "SELECT * FROM recipes WHERE 1=1";
        $params = [];

        if (!empty($filters['category'])) {
            $sql .= " AND category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['season'])) {
            $sql .= " AND JSON_CONTAINS(season, :season)";
            $params['season'] = json_encode($filters['season']);
        }

        if (!empty($filters['diet_type'])) {
            $sql .= " AND JSON_CONTAINS(dietary_restrictions, :diet_type)";
            $params['diet_type'] = json_encode($filters['diet_type']);
        }

        if (!empty($filters['allergens'])) {
            $sql .= " AND NOT JSON_OVERLAPS(allergens, :allergens)";
            $params['allergens'] = json_encode($filters['allergens']);
        }

        if (!empty($filters['max_prep_time'])) {
            $sql .= " AND (prep_time + cook_time) <= :max_prep_time";
            $params['max_prep_time'] = $filters['max_prep_time'];
        }

        $sql .= " ORDER BY title->>'$.fr' ASC";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);

        $recipes = [];
        while ($row = $stmt->fetch()) {
            $recipes[] = $this->hydrateRecipe($row);
        }

        return $recipes;
    }

    public function findById(string $id): ?Recipe
    {
        $sql = "SELECT * FROM recipes WHERE id = :id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->hydrateRecipe($row);
    }

    public function findBySeason(array $seasons): array
    {
        $sql = "SELECT * FROM recipes WHERE JSON_OVERLAPS(season, :seasons)";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute(['seasons' => json_encode($seasons)]);

        $recipes = [];
        while ($row = $stmt->fetch()) {
            $recipes[] = $this->hydrateRecipe($row);
        }

        return $recipes;
    }

    public function findByDietType(string $dietType): array
    {
        $sql = "SELECT * FROM recipes WHERE JSON_CONTAINS(dietary_restrictions, :diet_type)";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute(['diet_type' => json_encode($dietType)]);

        $recipes = [];
        while ($row = $stmt->fetch()) {
            $recipes[] = $this->hydrateRecipe($row);
        }

        return $recipes;
    }

    public function findFavoritesByUserId(int $userId): array
    {
        $sql = "SELECT r.* FROM recipes r 
                INNER JOIN user_favorites uf ON r.id = uf.recipe_id 
                WHERE uf.user_id = :user_id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        $recipes = [];
        while ($row = $stmt->fetch()) {
            $recipes[] = $this->hydrateRecipe($row);
        }

        return $recipes;
    }

    public function toggleFavorite(int $userId, string $recipeId): bool
    {
        $sql = "SELECT * FROM user_favorites WHERE user_id = :user_id AND recipe_id = :recipe_id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'recipe_id' => $recipeId]);

        if ($stmt->fetch()) {
            // Supprimer des favoris
            $sql = "DELETE FROM user_favorites WHERE user_id = :user_id AND recipe_id = :recipe_id";
            $stmt = $this->db->getConnection()->prepare($sql);
            return $stmt->execute(['user_id' => $userId, 'recipe_id' => $recipeId]);
        } else {
            // Ajouter aux favoris
            $sql = "INSERT INTO user_favorites (user_id, recipe_id, created_at) VALUES (:user_id, :recipe_id, NOW())";
            $stmt = $this->db->getConnection()->prepare($sql);
            return $stmt->execute(['user_id' => $userId, 'recipe_id' => $recipeId]);
        }
    }

    public function getRandomRecipes(int $limit, array $filters = []): array
    {
        $sql = "SELECT * FROM recipes WHERE 1=1";
        $params = [];

        if (!empty($filters['category'])) {
            $sql .= " AND category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['season'])) {
            $sql .= " AND JSON_CONTAINS(season, :season)";
            $params['season'] = json_encode($filters['season']);
        }

        $sql .= " ORDER BY RAND() LIMIT :limit";
        $params['limit'] = $limit;

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute($params);

        $recipes = [];
        while ($row = $stmt->fetch()) {
            $recipes[] = $this->hydrateRecipe($row);
        }

        return $recipes;
    }

    private function hydrateRecipe(array $row): Recipe
    {
        return new Recipe(
            $row['id'],
            json_decode($row['title'] ?? '{}', true) ?: [],
            json_decode($row['description'] ?? '{}', true) ?: [],
            $row['category'],
            json_decode($row['season'] ?? '[]', true) ?: [],
            (int) $row['prep_time'],
            (int) $row['cook_time'],
            (int) $row['servings'],
            $row['difficulty'],
            json_decode($row['nutrition'] ?? '{}', true) ?: [],
            json_decode($row['allergens'] ?? '[]', true) ?: [],
            json_decode($row['dietary_restrictions'] ?? '[]', true) ?: [],
            $this->getIngredientsForRecipe($row['id']),
            json_decode($row['instructions'] ?? '[]', true) ?: [],
            json_decode($row['tags'] ?? '[]', true) ?: [],
            $row['image_url'] ?? null
        );
    }

    private function getIngredientsForRecipe(string $recipeId): array
    {
        $sql = "SELECT * FROM recipe_ingredients WHERE recipe_id = :recipe_id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute(['recipe_id' => $recipeId]);

        $ingredients = [];
        while ($row = $stmt->fetch()) {
            $ingredients[] = new Ingredient(
                json_decode($row['name'] ?? '{}', true) ?: [],
                json_decode($row['quantity'] ?? '{}', true) ?: [],
                (bool) $row['seasonal'],
                json_decode($row['season'] ?? '[]', true) ?: []
            );
        }

        return $ingredients;
    }
}
