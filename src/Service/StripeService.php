<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Customer;
use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Event;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * StripeService — subscription management via Stripe.
 *
 * Requirements:
 *   composer require stripe/stripe-php
 *
 * Required env variables:
 *   STRIPE_SECRET_KEY=sk_test_...
 *   STRIPE_WEBHOOK_SECRET=whsec_...
 *   STRIPE_PRICE_GROWTH=price_...
 *   STRIPE_PRICE_PRO=price_...
 *   STRIPE_PRICE_SCALE=price_...
 *   STRIPE_PRICE_ENTERPRISE=price_...
 *   FRONTEND_URL=http://localhost:5173
 */
class StripeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $stripeSecretKey,
        private readonly string $stripeWebhookSecret,
        private readonly string $priceGrowth,
        private readonly string $pricePro,
        private readonly string $priceScale,
        private readonly string $priceEnterprise,
        private readonly string $alertFromEmail = 'billing@cultivatrace.app',
        // Legacy price IDs kept during the migration window — remove once all subscriptions have migrated
        private readonly string $priceStarter = '',
        private readonly string $priceBusiness = '',
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Creates a Stripe Checkout session.
     * Returns the URL to redirect the user to.
     */
    public function createCheckoutSession(
        Organization $organization,
        SubscriptionPlan $plan,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $priceId = $this->getPriceId($plan);

        $params = [
            'mode'                 => 'subscription',
            'line_items'           => [['price' => $priceId, 'quantity' => 1]],
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
            'client_reference_id' => (string) $organization->getId(),
            'metadata'             => [
                'organization_id' => (string) $organization->getId(),
                'plan'            => $plan->value,
            ],
            'subscription_data'   => [
                'metadata' => [
                    'organization_id' => (string) $organization->getId(),
                ],
            ],
        ];

        // Reuse existing Stripe customer if available
        if ($organization->getStripeCustomerId()) {
            $params['customer'] = $organization->getStripeCustomerId();
        } else {
            $firstUser = $organization->getUsers()->first();
            $params['customer_email'] = $firstUser instanceof User ? $firstUser->getEmail() : null;
        }

        // 1-hour window: deduplicates double-clicks and short retries while
        // ensuring each new attempt after an hour gets a fresh session.
        $window = (string) (int) (time() / 3600);
        $idempotencyKey = sprintf(
            'checkout-%s-%s-%s',
            (string) $organization->getId(),
            $plan->value,
            $window
        );

        $session = Session::create($params, ['idempotencyKey' => $idempotencyKey]);
        return $session->url;
    }

    public function createCustomer(
        Organization $organization,
        string $email,
        SubscriptionPlan $selectedPlan = SubscriptionPlan::GROWTH,
    ): string {
        $customer = Customer::create([
            'email' => $email,
            'name' => $organization->getName(),
            'metadata' => [
                'organization_id' => (string) $organization->getId(),
                'organization_name' => $organization->getName(),
                'selected_plan' => $selectedPlan->value,
            ],
        ]);

        return $customer->id;
    }

    /**
     * Creates a Stripe Customer Portal session.
     */
    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        $session = PortalSession::create([
            'customer'   => $customerId,
            'return_url' => $returnUrl,
        ]);
        return $session->url;
    }

    public function syncCheckoutSession(string $sessionId, Organization $organization): SubscriptionPlan
    {
        $session = Session::retrieve($sessionId);

        if (($session->payment_status ?? null) !== 'paid' && ($session->status ?? null) !== 'complete') {
            throw new \InvalidArgumentException('Stripe checkout session is not completed yet.');
        }

        $sessionOrganizationId = $session->metadata->organization_id ?? $session->client_reference_id ?? null;
        if ($sessionOrganizationId !== (string) $organization->getId()) {
            throw new \InvalidArgumentException('Stripe checkout session does not belong to the current organization.');
        }

        $plan = $session->metadata->plan ?? null;
        if (!is_string($plan) || $plan === '') {
            throw new \InvalidArgumentException('Stripe checkout session is missing the target plan.');
        }

        $planEnum = SubscriptionPlan::fromWebhookValue($plan);
        $organization->setPlan($planEnum);

        if (is_string($session->customer) && $session->customer !== '') {
            $organization->setStripeCustomerId($session->customer);
        }

        $this->em->flush();

        return $planEnum;
    }

    public function syncOrganizationSubscription(Organization $organization): SubscriptionPlan
    {
        $customerId = $organization->getStripeCustomerId();
        if ($customerId === null || trim($customerId) === '') {
            return $organization->getPlan();
        }

        $subscriptions = Subscription::all([
            'customer' => $customerId,
            'status' => 'all',
            'limit' => 10,
        ]);

        foreach ($subscriptions->data as $subscription) {
            $status = (string) ($subscription->status ?? '');
            if (!in_array($status, ['active', 'trialing', 'past_due', 'unpaid'], true)) {
                continue;
            }

            $priceId = $subscription->items->data[0]->price->id ?? null;
            if (!is_string($priceId) || $priceId === '') {
                continue;
            }

            $plan = $this->resolvePlanFromPriceId($priceId);
            $organization->setPlan($plan);
            $this->em->flush();

            return $plan;
        }

        return $organization->getPlan();
    }

    /**
     * Handles a Stripe webhook.
     * Verifies the signature before processing the event.
     *
     * @throws \InvalidArgumentException if business processing rejects the event
     * @throws \Stripe\Exception\SignatureVerificationException if the Stripe signature is invalid
     * @throws \UnexpectedValueException if the Stripe payload is invalid
     */
    public function handleWebhook(string $payload, string $signature): void
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            $this->stripeWebhookSecret
        );

        $this->logger->info('[Stripe] Webhook received', ['event_type' => $event->type]);

        match ($event->type) {
            'checkout.session.completed'     => $this->onCheckoutCompleted($event),
            'customer.subscription.deleted'  => $this->onSubscriptionDeleted($event),
            'invoice.payment_failed'         => $this->onPaymentFailed($event),
            'invoice.payment_succeeded'      => $this->onPaymentSucceeded($event),
            default                          => null,
        };
    }

    private function onCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;
        $orgId   = $session->metadata->organization_id ?? $session->client_reference_id ?? null;
        $plan    = $session->metadata->plan ?? null;

        if (!$orgId || !$plan) {
            $this->logger->error('[Stripe] checkout.session.completed missing organization_id or plan');
            return;
        }

        $org = $this->em->getRepository(Organization::class)->find($orgId);
        if (!$org) {
            $this->logger->error('[Stripe] Organization not found', ['org_id' => $orgId]);
            return;
        }

        $org->setPlan(SubscriptionPlan::fromWebhookValue($plan));
        $org->setStripeCustomerId($session->customer);
        $this->em->flush();

        $this->logger->info('[Stripe] Organization upgraded to plan', [
            'org_id'   => $orgId,
            'plan'     => $plan,
            'customer' => $this->maskCustomerId((string) $session->customer),
        ]);

        // Confirmation email
        $firstUser = $org->getUsers()->first();
        $userEmail = $firstUser instanceof User ? $firstUser->getEmail() : null;
        if ($userEmail) {
            $email = (new Email())
                ->from($this->alertFromEmail)
                ->to($userEmail)
                ->subject('✅ CultivaTrace subscription activated — ' . ucfirst($plan) . ' plan')
                ->text(sprintf(
                    "Your CultivaTrace %s plan subscription is now active.\n\n" .
                    "You can manage your subscription from your billing dashboard.\n\n" .
                    "Thank you for trusting CultivaTrace!",
                    ucfirst($plan)
                ));
            $this->mailer->send($email);
        }
    }

    private function onSubscriptionDeleted(Event $event): void
    {
        $subscription = $event->data->object;
        $customerId   = $subscription->customer;

        $org = $this->em->getRepository(Organization::class)
            ->findOneBy(['stripeCustomerId' => $customerId]);

        if (!$org) {
            $this->logger->warning('[Stripe] Organization not found for customer', [
                'customer' => $this->maskCustomerId((string) $customerId),
            ]);
            return;
        }

        $org->setPlan(SubscriptionPlan::GROWTH);
        $org->setLicenseStatus(LicenseStatus::SUSPENDED);
        $this->em->flush();

        $this->logger->info('[Stripe] Subscription cancelled', [
            'org_id'   => (string) $org->getId(),
            'customer' => $this->maskCustomerId((string) $customerId),
        ]);
    }

    private function onPaymentFailed(Event $event): void
    {
        $invoice    = $event->data->object;
        $customerId = $invoice->customer;

        $org = $this->em->getRepository(Organization::class)
            ->findOneBy(['stripeCustomerId' => $customerId]);

        if (!$org) return;

        $firstUser = $org->getUsers()->first();
        $userEmail = $firstUser instanceof User ? $firstUser->getEmail() : null;
        if ($userEmail) {
            $email = (new Email())
                ->from($this->alertFromEmail)
                ->to($userEmail)
                ->subject('⚠️ Payment failed — Action required')
                ->text(
                    "Your CultivaTrace subscription renewal has failed.\n\n" .
                    "Please update your payment method from your billing dashboard.\n" .
                    "Without action within 7 days, your access will be suspended."
                );
            $this->mailer->send($email);
        }
    }

    private function onPaymentSucceeded(Event $event): void
    {
        $invoice    = $event->data->object;
        $customerId = $invoice->customer;

        $org = $this->em->getRepository(Organization::class)
            ->findOneBy(['stripeCustomerId' => $customerId]);

        if (!$org) {
            $this->logger->info('[Stripe] Payment succeeded — organization not found', [
                'customer' => $this->maskCustomerId((string) $customerId),
            ]);
            return;
        }

        try {
            $this->syncOrganizationSubscription($org);

            // If org was billing-suspended (onSubscriptionDeleted), restore access.
            // PENDING / REJECTED / EXPIRED are intentionally left untouched.
            if ($org->getLicenseStatus() === LicenseStatus::SUSPENDED) {
                $org->setLicenseStatus(LicenseStatus::ACTIVE);
                $this->em->flush();
            }

            $this->logger->info('[Stripe] Payment succeeded — subscription re-synced', [
                'org_id'   => (string) $org->getId(),
                'customer' => $this->maskCustomerId((string) $customerId),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Stripe] Resync failed after successful payment', ['error' => $e->getMessage()]);
        }
    }

    private function getPriceId(SubscriptionPlan $plan): string
    {
        $priceId = match ($plan) {
            SubscriptionPlan::GROWTH     => $this->priceGrowth,
            SubscriptionPlan::PRO        => $this->pricePro,
            SubscriptionPlan::SCALE      => $this->priceScale,
            SubscriptionPlan::ENTERPRISE => $this->priceEnterprise,
        };

        $priceId = trim($priceId);
        if ($priceId === '') {
            throw new \InvalidArgumentException(sprintf('Stripe price ID is missing for the "%s" plan.', $plan->value));
        }

        if (!str_starts_with($priceId, 'price_')) {
            throw new \InvalidArgumentException(sprintf(
                'Stripe price ID for the "%s" plan is invalid. Expected a Stripe price_ identifier, got "%s".',
                $plan->value,
                $priceId,
            ));
        }

        return $priceId;
    }

    private function resolvePlanFromPriceId(string $priceId): SubscriptionPlan
    {
        // Current price IDs
        if ($priceId === trim($this->priceGrowth))     return SubscriptionPlan::GROWTH;
        if ($priceId === trim($this->pricePro))        return SubscriptionPlan::PRO;
        if ($priceId === trim($this->priceScale))      return SubscriptionPlan::SCALE;
        if ($priceId === trim($this->priceEnterprise)) return SubscriptionPlan::ENTERPRISE;

        // Legacy price IDs — pre-rename subscriptions still carry old Starter/Business IDs
        if ($this->priceStarter !== '' && $priceId === trim($this->priceStarter))   return SubscriptionPlan::GROWTH;
        if ($this->priceBusiness !== '' && $priceId === trim($this->priceBusiness)) return SubscriptionPlan::SCALE;

        throw new \InvalidArgumentException(sprintf(
            'Unknown Stripe price ID "%s" returned by subscription sync.',
            $priceId,
        ));
    }

    private function maskCustomerId(string $customerId): string
    {
        if (strlen($customerId) <= 8) {
            return '****';
        }
        return substr($customerId, 0, 4) . str_repeat('*', strlen($customerId) - 8) . substr($customerId, -4);
    }
}
