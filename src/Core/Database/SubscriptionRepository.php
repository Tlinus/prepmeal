<?php

declare(strict_types=1);

namespace PrepMeal\Core\Database;

class SubscriptionRepository
{
    private DatabaseConnection $db;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM subscriptions WHERE id = ?"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    public function create(array $subscriptionData): int
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO subscriptions (user_id, plan_type, stripe_subscription_id, status, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $subscriptionData['user_id'],
            $subscriptionData['plan_type'],
            $subscriptionData['stripe_subscription_id'] ?? null,
            $subscriptionData['status'] ?? 'active'
        ]);
        
        return (int) $this->db->getConnection()->lastInsertId();
    }

    public function update(int $id, array $subscriptionData): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE subscriptions SET 
             plan_type = ?, 
             stripe_subscription_id = ?, 
             status = ?, 
             updated_at = NOW() 
             WHERE id = ?"
        );
        
        return $stmt->execute([
            $subscriptionData['plan_type'],
            $subscriptionData['stripe_subscription_id'] ?? null,
            $subscriptionData['status'] ?? 'active',
            $id
        ]);
    }

    public function cancel(int $id): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE subscriptions SET status = 'cancelled', updated_at = NOW() WHERE id = ?"
        );
        
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM subscriptions WHERE id = ?"
        );
        
        return $stmt->execute([$id]);
    }

    public function getActiveSubscriptions(): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM subscriptions WHERE status = 'active' ORDER BY created_at DESC"
        );
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function getExpiredSubscriptions(): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM subscriptions WHERE status = 'expired' ORDER BY created_at DESC"
        );
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}

