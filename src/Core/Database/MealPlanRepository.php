<?php

declare(strict_types=1);

namespace PrepMeal\Core\Database;

use PrepMeal\Core\Models\MealPlan;
use PrepMeal\Core\Models\MealPlanDay;
use PrepMeal\Core\Models\Recipe;
use PDO;
use PDOException;

class MealPlanRepository
{
    private DatabaseConnection $dbConnection;

    public function __construct(DatabaseConnection $dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function save(MealPlan $plan, int $userId): bool
    {
        $pdo = $this->dbConnection->getConnection();
        
        try {
            $pdo->beginTransaction();

            // Sauvegarder le planning principal
            $stmt = $pdo->prepare("
                INSERT INTO meal_plans (id, user_id, title, start_date, end_date, preferences) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $plan->getId(),
                $userId,
                'Planning ' . $plan->getStartDate()->format('d/m/Y') . ' - ' . $plan->getEndDate()->format('d/m/Y'),
                $plan->getStartDate()->format('Y-m-d'),
                $plan->getEndDate()->format('Y-m-d'),
                json_encode($plan->getPreferences())
            ]);

            // Sauvegarder les jours du planning
            foreach ($plan->getDays() as $day) {
                $this->saveMealPlanDay($plan->getId(), $day);
            }

            $pdo->commit();
            return true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function saveMealPlanDay(string $planId, MealPlanDay $day): void
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO meal_plan_days (plan_id, date, meals) 
            VALUES (?, ?, ?)
        ");
        
        $meals = [];
        foreach ($day->getMeals() as $mealType => $recipe) {
            $meals[$mealType] = [
                'recipe_id' => $recipe->getId(),
                'title' => $recipe->getTitles(),
                'category' => $recipe->getCategory(),
                'prep_time' => $recipe->getPrepTime(),
                'cook_time' => $recipe->getCookTime(),
                'servings' => $recipe->getServings(),
                'nutrition' => $recipe->getNutrition()
            ];
        }
        
        $stmt->execute([
            $planId,
            $day->getDate()->format('Y-m-d'),
            json_encode($meals)
        ]);
    }

    public function findByUserId(int $userId, string $locale = 'fr'): array
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, title, start_date, end_date, preferences, created_at 
            FROM meal_plans 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$userId]);
        $plans = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $plan = $this->createMealPlanFromRow($row, $locale);
            if ($plan) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    public function findById(string $planId, int $userId, string $locale = 'fr'): ?MealPlan
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, title, start_date, end_date, preferences, created_at 
            FROM meal_plans 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$planId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->createMealPlanFromRow($row, $locale);
    }

    private function createMealPlanFromRow(array $row, string $locale): ?MealPlan
    {
        try {
            $startDate = new \DateTime($row['start_date']);
            $endDate = new \DateTime($row['end_date']);
            $preferences = json_decode($row['preferences'], true) ?? [];

            // Récupérer les jours du planning
            $days = $this->getMealPlanDays($row['id'], $locale);

            return new MealPlan(
                $row['id'],
                $startDate,
                $endDate,
                $days,
                $preferences
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getMealPlanDays(string $planId, string $locale): array
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT date, meals 
            FROM meal_plan_days 
            WHERE plan_id = ? 
            ORDER BY date
        ");
        
        $stmt->execute([$planId]);
        $days = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = new \DateTime($row['date']);
            $meals = json_decode($row['meals'], true) ?? [];
            
            $dayMeals = [];
            foreach ($meals as $mealType => $mealData) {
                $recipe = $this->createRecipeFromMealData($mealData, $locale);
                if ($recipe) {
                    $dayMeals[$mealType] = $recipe;
                }
            }
            
            $days[] = new MealPlanDay($date, $dayMeals);
        }

        return $days;
    }

    private function createRecipeFromMealData(array $mealData, string $locale): ?Recipe
    {
        try {
            return new Recipe(
                $mealData['recipe_id'],
                $mealData['title'] ?? [],
                ['fr' => '', 'en' => '', 'es' => '', 'de' => ''], // Description vide
                $mealData['category'] ?? 'equilibre',
                [], // Season
                $mealData['prep_time'] ?? 0,
                $mealData['cook_time'] ?? 0,
                $mealData['servings'] ?? 2,
                'facile', // Difficulty
                $mealData['nutrition'] ?? [],
                [], // Allergens
                [], // Dietary restrictions
                [], // Ingredients
                [], // Instructions
                [] // Tags
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    public function update(MealPlan $plan): bool
    {
        $pdo = $this->dbConnection->getConnection();
        
        try {
            $pdo->beginTransaction();

            // Mettre à jour le planning principal
            $stmt = $pdo->prepare("
                UPDATE meal_plans 
                SET title = ?, start_date = ?, end_date = ?, preferences = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            $stmt->execute([
                'Planning ' . $plan->getStartDate()->format('d/m/Y') . ' - ' . $plan->getEndDate()->format('d/m/Y'),
                $plan->getStartDate()->format('Y-m-d'),
                $plan->getEndDate()->format('Y-m-d'),
                json_encode($plan->getPreferences()),
                $plan->getId()
            ]);

            // Supprimer les anciens jours
            $stmt = $pdo->prepare("DELETE FROM meal_plan_days WHERE plan_id = ?");
            $stmt->execute([$plan->getId()]);

            // Sauvegarder les nouveaux jours
            foreach ($plan->getDays() as $day) {
                $this->saveMealPlanDay($plan->getId(), $day);
            }

            $pdo->commit();
            return true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(string $planId, int $userId): bool
    {
        $pdo = $this->dbConnection->getConnection();
        
        try {
            $pdo->beginTransaction();

            // Supprimer les jours du planning
            $stmt = $pdo->prepare("DELETE FROM meal_plan_days WHERE plan_id = ?");
            $stmt->execute([$planId]);

            // Supprimer le planning principal
            $stmt = $pdo->prepare("DELETE FROM meal_plans WHERE id = ? AND user_id = ?");
            $stmt->execute([$planId, $userId]);

            $pdo->commit();
            return true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getPlanStats(int $userId): array
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_plans,
                COUNT(CASE WHEN start_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_plans,
                MIN(start_date) as first_plan_date,
                MAX(start_date) as last_plan_date
            FROM meal_plans 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
    }

    public function searchPlans(int $userId, array $filters = []): array
    {
        $pdo = $this->dbConnection->getConnection();
        
        $sql = "SELECT id, title, start_date, end_date, preferences, created_at FROM meal_plans WHERE user_id = ?";
        $params = [$userId];

        // Ajouter les filtres
        if (!empty($filters['start_date'])) {
            $sql .= " AND start_date >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND end_date <= ?";
            $params[] = $filters['end_date'];
        }

        if (!empty($filters['diet_type'])) {
            $sql .= " AND JSON_EXTRACT(preferences, '$.diet_type') = ?";
            $params[] = $filters['diet_type'];
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $plans = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $plan = $this->createMealPlanFromRow($row, $filters['locale'] ?? 'fr');
            if ($plan) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }
}

