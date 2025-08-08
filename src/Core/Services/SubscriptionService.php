<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

use PrepMeal\Core\Database\DatabaseConnection;

class SubscriptionService
{
    private DatabaseConnection $db;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function getAvailablePlans(): array
    {
        return [
            [
                'id' => 'price_free',
                'name' => 'Gratuit',
                'price' => 0,
                'currency' => 'EUR',
                'period' => 'month',
                'features' => [
                    'planning_7_days' => true,
                    'basic_recipes' => true,
                    'seasonal_ingredients' => true,
                    'favorites' => true,
                    'export_pdf' => false,
                    'export_ical' => false,
                    'unlimited_planning' => false,
                    'premium_recipes' => false,
                    'nutritional_analysis' => false,
                    'shopping_list' => false
                ],
                'stripe_price_id' => null
            ],
            [
                'id' => 'price_monthly',
                'name' => 'Mensuel',
                'price' => 999, // 9.99 EUR en centimes
                'currency' => 'EUR',
                'period' => 'month',
                'features' => [
                    'planning_7_days' => true,
                    'basic_recipes' => true,
                    'seasonal_ingredients' => true,
                    'favorites' => true,
                    'export_pdf' => true,
                    'export_ical' => true,
                    'unlimited_planning' => true,
                    'premium_recipes' => true,
                    'nutritional_analysis' => true,
                    'shopping_list' => true
                ],
                'stripe_price_id' => $_ENV['STRIPE_PRICE_MONTHLY'] ?? 'price_monthly_id'
            ],
            [
                'id' => 'price_yearly',
                'name' => 'Annuel',
                'price' => 9990, // 99.90 EUR en centimes (2 mois gratuits)
                'currency' => 'EUR',
                'period' => 'year',
                'features' => [
                    'planning_7_days' => true,
                    'basic_recipes' => true,
                    'seasonal_ingredients' => true,
                    'favorites' => true,
                    'export_pdf' => true,
                    'export_ical' => true,
                    'unlimited_planning' => true,
                    'premium_recipes' => true,
                    'nutritional_analysis' => true,
                    'shopping_list' => true,
                    'priority_support' => true,
                    'early_access_features' => true
                ],
                'stripe_price_id' => $_ENV['STRIPE_PRICE_YEARLY'] ?? 'price_yearly_id'
            ]
        ];
    }

    public function getUserSubscription(int $userId): ?array
    {
        $sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status IN ('active', 'past_due') ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return null;
        }

        // Ajouter les informations du plan
        $plans = $this->getAvailablePlans();
        $plan = array_filter($plans, fn($p) => $p['id'] === $subscription['plan_type']);
        $subscription['plan'] = reset($plan);

        return $subscription;
    }

    public function saveSubscription(int $userId, $stripeSubscription): void
    {
        $sql = "INSERT INTO subscriptions (user_id, stripe_subscription_id, plan_type, status, current_period_start, current_period_end) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $userId,
            $stripeSubscription->id,
            $this->getPlanTypeFromStripePrice($stripeSubscription->items->data[0]->price->id),
            $stripeSubscription->status,
            date('Y-m-d H:i:s', $stripeSubscription->current_period_start),
            date('Y-m-d H:i:s', $stripeSubscription->current_period_end)
        ]);
    }

    public function updateSubscriptionStatus(string $stripeCustomerId, string $stripeSubscriptionId, string $status): void
    {
        // Récupérer l'utilisateur par le customer ID Stripe
        $user = $this->getUserByStripeCustomerId($stripeCustomerId);
        
        if (!$user) {
            return;
        }

        $sql = "UPDATE subscriptions SET status = ? WHERE stripe_subscription_id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $stripeSubscriptionId, $user['id']]);
    }

    public function cancelSubscription(int $userId): void
    {
        $sql = "UPDATE subscriptions SET status = 'canceled', canceled_at = NOW() WHERE user_id = ? AND status IN ('active', 'past_due')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
    }

    public function cancelSubscriptionByStripeId(string $stripeSubscriptionId): void
    {
        $sql = "UPDATE subscriptions SET status = 'canceled', canceled_at = NOW() WHERE stripe_subscription_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$stripeSubscriptionId]);
    }

    public function getPaymentHistory(int $userId): array
    {
        $sql = "SELECT p.*, s.plan_type 
                FROM payments p 
                LEFT JOIN subscriptions s ON p.subscription_id = s.id 
                WHERE p.user_id = ? 
                ORDER BY p.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }

    public function recordPayment(string $stripeCustomerId, string $stripeSubscriptionId, int $amount, string $status): void
    {
        $user = $this->getUserByStripeCustomerId($stripeCustomerId);
        
        if (!$user) {
            return;
        }

        $subscription = $this->getSubscriptionByStripeId($stripeSubscriptionId);
        
        $sql = "INSERT INTO payments (user_id, subscription_id, stripe_payment_intent_id, amount, currency, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $user['id'],
            $subscription['id'] ?? null,
            uniqid('pi_'), // Générer un ID unique pour le payment intent
            $amount,
            'eur',
            $status
        ]);
    }

    public function getUser(int $userId): array
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetch() ?: [];
    }

    public function canAccessFeature(int $userId, string $feature): bool
    {
        $subscription = $this->getUserSubscription($userId);
        
        if (!$subscription) {
            // Utilisateur gratuit
            return $this->isFeatureAvailableInFreePlan($feature);
        }

        return $subscription['plan']['features'][$feature] ?? false;
    }

    public function getPlanLimits(int $userId): array
    {
        $subscription = $this->getUserSubscription($userId);
        
        if (!$subscription) {
            return [
                'planning_days' => 7,
                'recipes_per_day' => 3,
                'favorites_limit' => 10,
                'export_limit' => 0
            ];
        }

        switch ($subscription['plan_type']) {
            case 'monthly':
            case 'yearly':
                return [
                    'planning_days' => 365,
                    'recipes_per_day' => 5,
                    'favorites_limit' => -1, // Illimité
                    'export_limit' => -1 // Illimité
                ];
            default:
                return [
                    'planning_days' => 7,
                    'recipes_per_day' => 3,
                    'favorites_limit' => 10,
                    'export_limit' => 0
                ];
        }
    }

    private function getPlanTypeFromStripePrice(string $stripePriceId): string
    {
        $plans = $this->getAvailablePlans();
        
        foreach ($plans as $plan) {
            if ($plan['stripe_price_id'] === $stripePriceId) {
                return $plan['id'];
            }
        }
        
        return 'free';
    }

    private function getUserByStripeCustomerId(string $stripeCustomerId): ?array
    {
        $sql = "SELECT * FROM users WHERE stripe_customer_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$stripeCustomerId]);
        
        return $stmt->fetch() ?: null;
    }

    private function getSubscriptionByStripeId(string $stripeSubscriptionId): ?array
    {
        $sql = "SELECT * FROM subscriptions WHERE stripe_subscription_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$stripeSubscriptionId]);
        
        return $stmt->fetch() ?: null;
    }

    private function isFeatureAvailableInFreePlan(string $feature): bool
    {
        $freePlanFeatures = [
            'planning_7_days' => true,
            'basic_recipes' => true,
            'seasonal_ingredients' => true,
            'favorites' => true,
            'export_pdf' => false,
            'export_ical' => false,
            'unlimited_planning' => false,
            'premium_recipes' => false,
            'nutritional_analysis' => false,
            'shopping_list' => false
        ];

        return $freePlanFeatures[$feature] ?? false;
    }
}
