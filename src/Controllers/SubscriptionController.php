<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\SubscriptionService;
use PrepMeal\Core\Services\TranslationService;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\PaymentIntent;
use Stripe\Webhook;

class SubscriptionController extends BaseController
{
    private SubscriptionService $subscriptionService;
    private TranslationService $translationService;

    public function __construct(
        SubscriptionService $subscriptionService,
        TranslationService $translationService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->translationService = $translationService;
        
        // Initialiser Stripe
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    }

    public function index(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $plans = $this->subscriptionService->getAvailablePlans();
        $currentSubscription = $this->subscriptionService->getUserSubscription($userId);

        $data = [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'],
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['subscription', 'common'])
        ];

        return $this->render($response, 'subscription/index.twig', $data);
    }

    public function manage(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $subscription = $this->subscriptionService->getUserSubscription($userId);
        $paymentHistory = $this->subscriptionService->getPaymentHistory($userId);

        $data = [
            'subscription' => $subscription,
            'paymentHistory' => $paymentHistory,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['subscription', 'common'])
        ];

        return $this->render($response, 'subscription/manage.twig', $data);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();
        
        $planId = $data['plan_id'] ?? null;
        $paymentMethodId = $data['payment_method_id'] ?? null;

        if (!$planId || !$paymentMethodId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Données manquantes'
            ], 400);
        }

        try {
            $user = $this->subscriptionService->getUser($userId);
            
            // Créer ou récupérer le client Stripe
            $stripeCustomer = $this->getOrCreateStripeCustomer($user);
            
            // Attacher la méthode de paiement
            $this->attachPaymentMethod($stripeCustomer->id, $paymentMethodId);
            
            // Créer l'abonnement
            $subscription = $this->createStripeSubscription($stripeCustomer->id, $planId);
            
            // Sauvegarder en base
            $this->subscriptionService->saveSubscription($userId, $subscription);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'subscription_id' => $subscription->id,
                'message' => 'Abonnement créé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la création de l\'abonnement: ' . $e->getMessage()
            ], 400);
        }
    }

    public function cancel(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        
        try {
            $subscription = $this->subscriptionService->getUserSubscription($userId);
            
            if (!$subscription) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Aucun abonnement trouvé'
                ], 404);
            }

            // Annuler l'abonnement Stripe
            $stripeSubscription = Subscription::retrieve($subscription['stripe_subscription_id']);
            $stripeSubscription->cancel();
            
            // Mettre à jour en base
            $this->subscriptionService->cancelSubscription($userId);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Abonnement annulé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de l\'annulation: ' . $e->getMessage()
            ], 400);
        }
    }

    public function history(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $paymentHistory = $this->subscriptionService->getPaymentHistory($userId);

        $data = [
            'paymentHistory' => $paymentHistory,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['subscription', 'common'])
        ];

        return $this->render($response, 'subscription/history.twig', $data);
    }

    public function stripeWebhook(Request $request, Response $response): Response
    {
        $payload = $request->getBody()->getContents();
        $sigHeader = $request->getHeaderLine('Stripe-Signature');
        $webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'];

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Signature invalide'
            ], 400);
        }

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

        return $this->jsonResponse($response, ['received' => true]);
    }

    public function createPaymentIntent(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $amount = $data['amount'] ?? 0;
        
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'eur',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la création du paiement: ' . $e->getMessage()
            ], 400);
        }
    }

    private function getOrCreateStripeCustomer($user): Customer
    {
        // Vérifier si le client existe déjà
        $existingCustomers = Customer::all([
            'email' => $user['email'],
            'limit' => 1
        ]);

        if (!empty($existingCustomers->data)) {
            return $existingCustomers->data[0];
        }

        // Créer un nouveau client
        return Customer::create([
            'email' => $user['email'],
            'name' => $user['name'],
            'metadata' => [
                'user_id' => $user['id']
            ]
        ]);
    }

    private function attachPaymentMethod(string $customerId, string $paymentMethodId): void
    {
        $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
        $paymentMethod->attach([
            'customer' => $customerId,
        ]);
    }

    private function createStripeSubscription(string $customerId, string $planId): Subscription
    {
        return Subscription::create([
            'customer' => $customerId,
            'items' => [
                ['price' => $planId],
            ],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'expand' => ['latest_invoice.payment_intent'],
        ]);
    }

    private function handleSubscriptionCreated($subscription): void
    {
        $this->subscriptionService->updateSubscriptionStatus(
            $subscription->customer,
            $subscription->id,
            $subscription->status
        );
    }

    private function handleSubscriptionUpdated($subscription): void
    {
        $this->subscriptionService->updateSubscriptionStatus(
            $subscription->customer,
            $subscription->id,
            $subscription->status
        );
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        $this->subscriptionService->cancelSubscriptionByStripeId($subscription->id);
    }

    private function handlePaymentSucceeded($invoice): void
    {
        $this->subscriptionService->recordPayment(
            $invoice->customer,
            $invoice->subscription,
            $invoice->amount_paid,
            'succeeded'
        );
    }

    private function handlePaymentFailed($invoice): void
    {
        $this->subscriptionService->recordPayment(
            $invoice->customer,
            $invoice->subscription,
            $invoice->amount_due,
            'failed'
        );
    }
}
