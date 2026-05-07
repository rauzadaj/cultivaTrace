<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Enum\SubscriptionPlan;

final readonly class BillingCheckoutService
{
    public function __construct(
        private StripeService $stripeService,
    ) {
    }

    public function createCheckoutUrl(Organization $organization, string $plan, string $frontendUrl): string
    {
        $frontendUrl = rtrim($frontendUrl, '/');

        $planEnum = SubscriptionPlan::from($plan);

        // Always create a checkout session — Stripe will pre-fill the existing customer
        // so the user sees the correct plan and doesn't need to re-enter payment details.
        // The portal flow (manage/cancel subscription) is handled by POST /api/billing/portal.
        return $this->stripeService->createCheckoutSession(
            organization: $organization,
            plan: $planEnum,
            successUrl: $frontendUrl . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: $frontendUrl . '/billing/cancel',
        );
    }

    public function confirmCheckout(string $sessionId, Organization $organization): SubscriptionPlan
    {
        return $this->stripeService->syncCheckoutSession($sessionId, $organization);
    }
}
