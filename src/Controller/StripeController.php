<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Service\BillingCheckoutService;
use App\Service\PlanLimitsService;
use App\Service\StripeService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly BillingCheckoutService $billingCheckoutService,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {}

    /**
     * POST /api/billing/checkout
     *
     * Creates a Stripe Checkout session to subscribe to a plan.
     *
     * JSON body:
     *   plan : starter | pro | business
     */
    #[Route('/api/billing/checkout', methods: ['POST'])]
    public function checkout(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $organization = $this->assertOrganizationWriter($user);
        $data = json_decode($request->getContent(), true) ?? [];
        $plan = $data['plan'] ?? null;

        $validPlans = ['starter', 'pro', 'business', 'enterprise'];
        if (!in_array($plan, $validPlans, true)) {
            return $this->json([
                'error'       => 'Invalid plan',
                'valid_plans' => $validPlans,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $checkoutUrl = $this->billingCheckoutService->createCheckoutUrl(
                $organization,
                $plan,
                $this->frontendUrl,
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
     * Creates a Stripe Customer Portal session (manage subscription, invoices).
     */
    #[Route('/api/billing/portal', methods: ['POST'])]
    public function portal(#[CurrentUser] ?User $user): JsonResponse
    {
        $org = $this->assertOrganizationWriter($user);

        if (!$org->getStripeCustomerId()) {
            return $this->json(
                ['error' => 'No active subscription found'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $portalUrl = $this->stripe->createPortalSession(
                customerId: $org->getStripeCustomerId(),
                returnUrl: $this->frontendUrl . '/billing',
            );
        } catch (ApiErrorException $exception) {
            $this->logger->error('[Stripe] Portal session creation failed', [
                'organizationId' => (string) $org->getId(),
                'error' => $exception->getMessage(),
            ]);

            return $this->json([
                'error' => 'Stripe billing portal is currently unavailable.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json(['portalUrl' => $portalUrl]);
    }

    #[Route('/api/billing/checkout/confirm', methods: ['POST'])]
    public function confirmCheckout(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $organization = $this->assertOrganizationWriter($user);
        $data = json_decode($request->getContent(), true) ?? [];
        $sessionId = $data['sessionId'] ?? null;

        if (!is_string($sessionId) || trim($sessionId) === '') {
            return $this->json(['error' => 'Missing Stripe checkout session ID.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $plan = $this->billingCheckoutService->confirmCheckout(
                trim($sessionId),
                $organization,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ApiErrorException $exception) {
            $this->logger->error('[Stripe] Checkout confirmation failed', [
                'sessionId' => trim($sessionId),
                'organizationId' => (string) $organization->getId(),
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
     * Stripe webhook — receives payment events.
     * URL must be configured in the Stripe dashboard.
     *
     * Handled events:
     *   - checkout.session.completed → activate subscription
     *   - customer.subscription.deleted → suspend organization
     *   - invoice.payment_failed → send follow-up email
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

            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $exception) {
            $this->logger->error('[Stripe] Webhook handling failed', [
                'error' => $exception->getMessage(),
            ]);

            return new Response('Internal Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * GET /api/billing/status
     *
     * Retourne le statut d'abonnement de l'organisation courante.
     */
    #[Route('/api/billing/status', methods: ['GET'])]
    public function billingStatus(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User || !$user->hasOrganization()) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $org = $user->getOrganization();

        if ($org->getStripeCustomerId()) {
            try {
                $this->stripe->syncOrganizationSubscription($org);
            } catch (\Throwable $exception) {
                $this->logger->warning('[Stripe] Billing status sync failed', [
                    'organizationId' => (string) $org->getId(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $this->json([
            'plan'               => $org->getPlan()->value,
            'licenseStatus'      => $org->getLicenseStatus()->value,
            'stripeCustomerId'   => $org->getStripeCustomerId() ? '***' : null,
            'hasActiveSubscription' => $org->getStripeCustomerId() !== null,
            'limits'             => $this->planLimits->getLimits($org),
        ]);
    }

    private function assertOrganizationWriter(?User $user): Organization
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authenticated user required.');
        }

        if (!array_intersect($user->getRoles(), ['ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN'])) {
            throw $this->createAccessDeniedException('Only organization administrators can manage billing.');
        }

        if (!$user->hasOrganization()) {
            throw $this->createAccessDeniedException('Authenticated user must belong to an organization.');
        }

        return $user->getOrganization();
    }
}
