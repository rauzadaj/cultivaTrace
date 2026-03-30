<?php

namespace App\Controller;

use App\Enum\SubscriptionPlan;
use App\Service\PlanLimitsService;
use App\Service\StripeService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
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
        private readonly LoggerInterface $logger,
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

        try {
            $planEnum = SubscriptionPlan::from($plan);
            $checkoutUrl  = $this->stripe->createCheckoutSession(
                organization: $user->getOrganization(),
                plan: $planEnum,
                successUrl: $_ENV['FRONTEND_URL'] . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
                cancelUrl: $_ENV['FRONTEND_URL'] . '/billing/cancel',
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ApiErrorException $exception) {
            $this->logger->error('[Stripe] Checkout session creation failed', [
                'plan' => $plan,
                'error' => $exception->getMessage(),
            ]);

            return $this->json([
                'error' => 'Stripe checkout is currently unavailable for this plan.',
            ], Response::HTTP_BAD_GATEWAY);
        }

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

    #[Route('/api/billing/checkout/confirm', methods: ['POST'])]
    public function confirmCheckout(Request $request, #[CurrentUser] $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $sessionId = $data['sessionId'] ?? null;

        if (!is_string($sessionId) || trim($sessionId) === '') {
            return $this->json(['error' => 'Missing Stripe checkout session ID.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $plan = $this->stripe->syncCheckoutSession(trim($sessionId), $user->getOrganization());
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ApiErrorException $exception) {
            $this->logger->error('[Stripe] Checkout confirmation failed', [
                'sessionId' => trim($sessionId),
                'organizationId' => (string) $user->getOrganization()->getId(),
                'error' => $exception->getMessage(),
            ]);

            return $this->json([
                'error' => 'Unable to confirm Stripe checkout session.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'plan' => $plan->value,
            'message' => 'Organization updated from Stripe checkout session.',
        ]);
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
        } catch (\InvalidArgumentException $exception) {
            $this->logger->warning('[Stripe] Invalid webhook signature', [
                'error' => $exception->getMessage(),
            ]);

            return new Response('OK', Response::HTTP_OK);
        } catch (\Throwable $exception) {
            $this->logger->error('[Stripe] Webhook handling failed', [
                'error' => $exception->getMessage(),
            ]);

            return new Response('OK', Response::HTTP_OK);
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
