<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\StripeSubscriptionService;
use PrepMeal\Core\Services\TranslationService;

class SubscriptionController extends BaseController
{
    private StripeSubscriptionService $stripeService;
    private TranslationService $translationService;

    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        TranslationService $translationService,
        StripeSubscriptionService $stripeService
    ) {
        parent::__construct($view, $translationService);
        $this->stripeService = $stripeService;
        $this->translationService = $translationService;
    }

    public function index(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $subscriptions = $this->stripeService->getUserSubscriptions($userId);
        $currentSubscription = $this->getCurrentSubscription($subscriptions);
        $limits = $this->stripeService->getSubscriptionLimits($currentSubscription['plan_type'] ?? 'free');

        $data = [
            'subscriptions' => $subscriptions,
            'currentSubscription' => $currentSubscription,
            'limits' => $limits,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['subscription', 'common'])
        ];

        return $this->render($response, 'subscription/index.twig', $data);
    }

    public function createSubscription(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();

        $planType = $data['plan_type'] ?? '';
        $paymentMethodId = $data['payment_method_id'] ?? null;

        try {
            $result = $this->stripeService->createSubscription($userId, $planType, $paymentMethodId);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'subscription_id' => $result['subscription_id'],
                'status' => $result['status'],
                'client_secret' => $result['client_secret'],
                'message' => 'Abonnement créé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la création de l\'abonnement: ' . $e->getMessage()
            ], 400);
        }
    }

    public function cancelSubscription(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        $subscriptionId = $args['id'] ?? '';

        try {
            $success = $this->stripeService->cancelSubscription($subscriptionId, $userId);
            
            if ($success) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Abonnement annulé avec succès'
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Erreur lors de l\'annulation de l\'abonnement'
                ], 400);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de l\'annulation: ' . $e->getMessage()
            ], 400);
        }
    }

    public function reactivateSubscription(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        $subscriptionId = $args['id'] ?? '';

        try {
            $success = $this->stripeService->reactivateSubscription($subscriptionId, $userId);
            
            if ($success) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Abonnement réactivé avec succès'
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Erreur lors de la réactivation de l\'abonnement'
                ], 400);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la réactivation: ' . $e->getMessage()
            ], 400);
        }
    }

    public function webhook(Request $request, Response $response): Response
    {
        $payload = $request->getBody()->getContents();
        $signature = $request->getHeaderLine('Stripe-Signature');

        try {
            $success = $this->stripeService->handleWebhook($payload, $signature);
            
            if ($success) {
                return $response->withStatus(200);
            } else {
                return $response->withStatus(400);
            }

        } catch (\Exception $e) {
            error_log('Erreur webhook: ' . $e->getMessage());
            return $response->withStatus(400);
        }
    }

    public function getSubscription(Request $request, Response $response, array $args): Response
    {
        $userId = $this->getUserId($request);
        $subscriptionId = $args['id'] ?? '';

        try {
            $subscription = $this->stripeService->getSubscription($subscriptionId, $userId);
            
            if ($subscription) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'subscription' => $subscription
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Abonnement non trouvé'
                ], 404);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'abonnement: ' . $e->getMessage()
            ], 400);
        }
    }

    public function billing(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $subscriptions = $this->stripeService->getUserSubscriptions($userId);
        $currentSubscription = $this->getCurrentSubscription($subscriptions);

        $data = [
            'subscriptions' => $subscriptions,
            'currentSubscription' => $currentSubscription,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['subscription', 'common'])
        ];

        return $this->render($response, 'subscription/billing.twig', $data);
    }

    public function plans(Request $request, Response $response): Response
    {
        $locale = $this->getLocale($request);
        $userId = $this->getUserId($request);
        
        $currentSubscription = $this->getCurrentSubscription($this->stripeService->getUserSubscriptions($userId));
        $currentPlanType = $currentSubscription['plan_type'] ?? 'free';

        $plans = [
            'free' => [
                'name' => 'Gratuit',
                'price' => '0€',
                'period' => 'pour toujours',
                'features' => [
                    '1 planning de repas',
                    '7 jours de planning',
                    'Recettes de base',
                    'Support communautaire'
                ],
                'limits' => $this->stripeService->getSubscriptionLimits('free')
            ],
            'monthly' => [
                'name' => 'Mensuel',
                'price' => '9.99€',
                'period' => 'par mois',
                'features' => [
                    'Plannings illimités',
                    'Planning annuel',
                    'Toutes les recettes',
                    'Export PDF',
                    'Support email'
                ],
                'limits' => $this->stripeService->getSubscriptionLimits('monthly')
            ],
            'yearly' => [
                'name' => 'Annuel',
                'price' => '99.99€',
                'period' => 'par an',
                'features' => [
                    'Plannings illimités',
                    'Planning annuel',
                    'Toutes les recettes',
                    'Export PDF',
                    'Support prioritaire',
                    'Économisez 20%'
                ],
                'limits' => $this->stripeService->getSubscriptionLimits('yearly')
            ]
        ];

        $data = [
            'plans' => $plans,
            'currentPlanType' => $currentPlanType,
            'locale' => $locale,
            'translations' => $this->translationService->getTranslations($locale, ['subscription', 'common'])
        ];

        return $this->render($response, 'subscription/plans.twig', $data);
    }

    private function getCurrentSubscription(array $subscriptions): array
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription['status'] === 'active') {
                return $subscription;
            }
        }
        
        return [];
    }
}
