<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\PaymentIntent;
use Stripe\Invoice;
use Stripe\Exception\ApiErrorException;
use PrepMeal\Core\Database\DatabaseConnection;

class StripeSubscriptionService
{
    private DatabaseConnection $dbConnection;
    private string $stripeSecretKey;
    private string $stripePublishableKey;

    public function __construct(DatabaseConnection $dbConnection, string $stripeSecretKey, string $stripePublishableKey)
    {
        $this->dbConnection = $dbConnection;
        $this->stripeSecretKey = $stripeSecretKey;
        $this->stripePublishableKey = $stripePublishableKey;
        
        Stripe::setApiKey($this->stripeSecretKey);
    }

    public function createCustomer(int $userId, string $email, string $name): ?string
    {
        try {
            $customer = Customer::create([
                'email' => $email,
                'name' => $name,
                'metadata' => [
                    'user_id' => $userId
                ]
            ]);

            // Sauvegarder l'ID client en base
            $this->saveCustomerId($userId, $customer->id);

            return $customer->id;
        } catch (ApiErrorException $e) {
            error_log('Erreur Stripe lors de la création du client: ' . $e->getMessage());
            return null;
        }
    }

    public function createSubscription(int $userId, string $planType, string $paymentMethodId = null): array
    {
        try {
            $customerId = $this->getCustomerId($userId);
            if (!$customerId) {
                $customerId = $this->createCustomer($userId, $this->getUserEmail($userId), $this->getUserName($userId));
            }

            $priceId = $this->getPriceId($planType);
            if (!$priceId) {
                throw new \Exception('Type de plan invalide');
            }

            $subscriptionData = [
                'customer' => $customerId,
                'items' => [
                    ['price' => $priceId]
                ],
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent']
            ];

            if ($paymentMethodId) {
                $subscriptionData['default_payment_method'] = $paymentMethodId;
            }

            $subscription = Subscription::create($subscriptionData);

            // Sauvegarder l'abonnement en base
            $this->saveSubscription($userId, $subscription, $planType);

            return [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null
            ];

        } catch (ApiErrorException $e) {
            error_log('Erreur Stripe lors de la création de l\'abonnement: ' . $e->getMessage());
            throw new \Exception('Erreur lors de la création de l\'abonnement: ' . $e->getMessage());
        }
    }

    public function cancelSubscription(string $subscriptionId, int $userId): bool
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);
            
            // Vérifier que l'abonnement appartient à l'utilisateur
            if (!$this->subscriptionBelongsToUser($subscriptionId, $userId)) {
                throw new \Exception('Abonnement non trouvé');
            }

            $subscription->cancel_at_period_end = true;
            $subscription->save();

            // Mettre à jour le statut en base
            $this->updateSubscriptionStatus($subscriptionId, 'canceled');

            return true;

        } catch (ApiErrorException $e) {
            error_log('Erreur Stripe lors de l\'annulation: ' . $e->getMessage());
            return false;
        }
    }

    public function reactivateSubscription(string $subscriptionId, int $userId): bool
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);
            
            if (!$this->subscriptionBelongsToUser($subscriptionId, $userId)) {
                throw new \Exception('Abonnement non trouvé');
            }

            $subscription->cancel_at_period_end = false;
            $subscription->save();

            // Mettre à jour le statut en base
            $this->updateSubscriptionStatus($subscriptionId, 'active');

            return true;

        } catch (ApiErrorException $e) {
            error_log('Erreur Stripe lors de la réactivation: ' . $e->getMessage());
            return false;
        }
    }

    public function getSubscription(string $subscriptionId, int $userId): ?array
    {
        try {
            if (!$this->subscriptionBelongsToUser($subscriptionId, $userId)) {
                return null;
            }

            $subscription = Subscription::retrieve($subscriptionId);
            
            return [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'plan_type' => $this->getPlanTypeFromPriceId($subscription->items->data[0]->price->id)
            ];

        } catch (ApiErrorException $e) {
            error_log('Erreur Stripe lors de la récupération de l\'abonnement: ' . $e->getMessage());
            return null;
        }
    }

    public function getUserSubscriptions(int $userId): array
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, stripe_subscription_id, plan_type, status, current_period_start, current_period_end, canceled_at, created_at
            FROM subscriptions 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function handleWebhook(string $payload, string $signature): bool
    {
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->getWebhookSecret());
            
            switch ($event->type) {
                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;
                    
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;
                    
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;
                    
                case 'invoice.payment_succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;
                    
                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;
            }

            return true;

        } catch (\Exception $e) {
            error_log('Erreur webhook Stripe: ' . $e->getMessage());
            return false;
        }
    }

    private function handleSubscriptionCreated($subscription): void
    {
        $this->updateSubscriptionStatus($subscription->id, $subscription->status);
    }

    private function handleSubscriptionUpdated($subscription): void
    {
        $this->updateSubscriptionStatus($subscription->id, $subscription->status);
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        $this->updateSubscriptionStatus($subscription->id, 'canceled');
    }

    private function handlePaymentSucceeded($invoice): void
    {
        if ($invoice->subscription) {
            $this->updateSubscriptionStatus($invoice->subscription, 'active');
        }
    }

    private function handlePaymentFailed($invoice): void
    {
        if ($invoice->subscription) {
            $this->updateSubscriptionStatus($invoice->subscription, 'past_due');
        }
    }

    private function getPriceId(string $planType): ?string
    {
        $priceIds = [
            'monthly' => $_ENV['STRIPE_MONTHLY_PRICE_ID'] ?? null,
            'yearly' => $_ENV['STRIPE_YEARLY_PRICE_ID'] ?? null
        ];

        return $priceIds[$planType] ?? null;
    }

    private function getPlanTypeFromPriceId(string $priceId): string
    {
        $priceIds = [
            $_ENV['STRIPE_MONTHLY_PRICE_ID'] ?? '' => 'monthly',
            $_ENV['STRIPE_YEARLY_PRICE_ID'] ?? '' => 'yearly'
        ];

        return $priceIds[$priceId] ?? 'free';
    }

    private function saveCustomerId(int $userId, string $customerId): void
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET stripe_customer_id = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([$customerId, $userId]);
    }

    private function getCustomerId(int $userId): ?string
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("SELECT stripe_customer_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['stripe_customer_id'] ?? null;
    }

    private function saveSubscription(int $userId, $subscription, string $planType): void
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO subscriptions (user_id, stripe_subscription_id, plan_type, status, current_period_start, current_period_end) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $subscription->id,
            $planType,
            $subscription->status,
            date('Y-m-d H:i:s', $subscription->current_period_start),
            date('Y-m-d H:i:s', $subscription->current_period_end)
        ]);
    }

    private function updateSubscriptionStatus(string $subscriptionId, string $status): void
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE stripe_subscription_id = ?
        ");
        
        $stmt->execute([$status, $subscriptionId]);
    }

    private function subscriptionBelongsToUser(string $subscriptionId, int $userId): bool
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM subscriptions 
            WHERE stripe_subscription_id = ? AND user_id = ?
        ");
        
        $stmt->execute([$subscriptionId, $userId]);
        return $stmt->fetchColumn() > 0;
    }

    private function getUserEmail(int $userId): string
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['email'] ?? '';
    }

    private function getUserName(int $userId): string
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['name'] ?? '';
    }

    private function getWebhookSecret(): string
    {
        return $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
    }

    public function getSubscriptionLimits(string $planType): array
    {
        $limits = [
            'free' => [
                'meal_plans' => 1,
                'planning_days' => 7,
                'recipes' => 'basic',
                'export_pdf' => false,
                'priority_support' => false
            ],
            'monthly' => [
                'meal_plans' => -1, // Illimité
                'planning_days' => -1, // Illimité
                'recipes' => 'all',
                'export_pdf' => true,
                'priority_support' => false
            ],
            'yearly' => [
                'meal_plans' => -1, // Illimité
                'planning_days' => -1, // Illimité
                'recipes' => 'all',
                'export_pdf' => true,
                'priority_support' => true
            ]
        ];

        return $limits[$planType] ?? $limits['free'];
    }

    public function canUserAccessFeature(int $userId, string $feature): bool
    {
        $subscription = $this->getActiveSubscription($userId);
        $planType = $subscription['plan_type'] ?? 'free';
        $limits = $this->getSubscriptionLimits($planType);

        return $limits[$feature] ?? false;
    }

    private function getActiveSubscription(int $userId): ?array
    {
        $pdo = $this->dbConnection->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT plan_type, status 
            FROM subscriptions 
            WHERE user_id = ? AND status = 'active' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}

