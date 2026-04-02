<?php

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

        if ($organization->getStripeCustomerId()) {
            return $this->stripeService->createPortalSession(
                customerId: $organization->getStripeCustomerId(),
                returnUrl: $frontendUrl . '/billing',
            );
        }

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
