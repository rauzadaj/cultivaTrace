<?php

namespace App\Controller;

use App\Enum\SubscriptionPlan;
use App\Service\PlanLimitsService;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class StripeController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly PlanLimitsService $planLimits,
    ) {}

    /**
     * POST /api/billing/checkout
     *
     * Crée une session Stripe Checkout pour s'abonner à un plan.
     *
     * Body JSON :
     *   plan : starter | pro | business
     */
    #[Route('/api/billing/checkout', methods: ['POST'])]
    public function checkout(Request $request, #[CurrentUser] $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $plan = $data['plan'] ?? null;

        $validPlans = ['starter', 'pro', 'business'];
        if (!in_array($plan, $validPlans, true)) {
            return $this->json([
                'error'       => 'Plan invalide',
                'valid_plans' => $validPlans,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $planEnum     = SubscriptionPlan::from($plan);
        $checkoutUrl  = $this->stripe->createCheckoutSession(
            organization: $user->getOrganization(),
            plan: $planEnum,
            successUrl: $_ENV['FRONTEND_URL'] . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: $_ENV['FRONTEND_URL'] . '/billing/cancel',
        );

        return $this->json(['checkoutUrl' => $checkoutUrl]);
    }

    /**
     * POST /api/billing/portal
     *
     * Crée une session Stripe Customer Portal (gérer l'abonnement, factures).
     */
    #[Route('/api/billing/portal', methods: ['POST'])]
    public function portal(#[CurrentUser] $user): JsonResponse
    {
        $org = $user->getOrganization();

        if (!$org->getStripeCustomerId()) {
            return $this->json(
                ['error' => 'Aucun abonnement actif trouvé'],
                Response::HTTP_NOT_FOUND
            );
        }

        $portalUrl = $this->stripe->createPortalSession(
            customerId: $org->getStripeCustomerId(),
            returnUrl: $_ENV['FRONTEND_URL'] . '/billing',
        );

        return $this->json(['portalUrl' => $portalUrl]);
    }

    /**
     * POST /api/billing/webhook
     *
     * Webhook Stripe — reçoit les événements de paiement.
     * URL à configurer dans le dashboard Stripe.
     *
     * Événements traités :
     *   - checkout.session.completed → activer l'abonnement
     *   - customer.subscription.deleted → suspendre l'organisation
     *   - invoice.payment_failed → envoyer email de relance
     */
    #[Route('/api/billing/webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature', '');

        try {
            $this->stripe->handleWebhook($payload, $signature);
        } catch (\InvalidArgumentException $e) {
            return new Response('Webhook signature invalid', Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            // Logger l'erreur mais retourner 200 pour éviter les retries Stripe
            return new Response('Webhook error logged', Response::HTTP_OK);
        }

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * GET /api/billing/status
     *
     * Retourne le statut d'abonnement de l'organisation courante.
     */
    #[Route('/api/billing/status', methods: ['GET'])]
    public function billingStatus(#[CurrentUser] $user): JsonResponse
    {
        $org = $user->getOrganization();

        return $this->json([
            'plan'               => $org->getPlan()->value,
            'licenseStatus'      => $org->getLicenseStatus()->value,
            'stripeCustomerId'   => $org->getStripeCustomerId() ? '***' : null,
            'hasActiveSubscription' => $org->getStripeCustomerId() !== null,
            'limits'             => $this->planLimits->getLimits($org),
        ]);
    }
}
