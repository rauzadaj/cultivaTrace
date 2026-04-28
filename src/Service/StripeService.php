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
 * StripeService — gestion des abonnements via Stripe.
 *
 * Prérequis :
 *   composer require stripe/stripe-php
 *
 * Variables d'env requises :
 *   STRIPE_SECRET_KEY=sk_test_...
 *   STRIPE_WEBHOOK_SECRET=whsec_...
 *   STRIPE_PRICE_STARTER=price_...
 *   STRIPE_PRICE_PRO=price_...
 *   STRIPE_PRICE_BUSINESS=price_...
 *   FRONTEND_URL=http://localhost:5173
 *
 * Création des prix Stripe :
 *   Dashboard Stripe → Products → Create product
 *   Un produit par plan (Starter 79€/mois, Pro 249€/mois, Business 599€/mois)
 */
class StripeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $stripeSecretKey,
        private readonly string $stripeWebhookSecret,
        private readonly string $priceStarter,
        private readonly string $pricePro,
        private readonly string $priceBusiness,
        private readonly string $alertFromEmail = 'billing@cannas.app',
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Crée une session Stripe Checkout.
     * Retourne l'URL vers laquelle rediriger l'utilisateur.
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

        // Réutiliser le customer Stripe existant si disponible
        if ($organization->getStripeCustomerId()) {
            $params['customer'] = $organization->getStripeCustomerId();
        } else {
            $firstUser = $organization->getUsers()->first();
            $params['customer_email'] = $firstUser instanceof User ? $firstUser->getEmail() : null;
        }

        $idempotencyKey = sprintf(
            'checkout-%s-%s-%s',
            (string) $organization->getId(),
            $plan->value,
            (new \DateTimeImmutable())->format('Ymd')
        );

        $session = Session::create($params, ['idempotencyKey' => $idempotencyKey]);
        return $session->url;
    }

    public function createCustomer(
        Organization $organization,
        string $email,
        SubscriptionPlan $selectedPlan = SubscriptionPlan::STARTER,
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
     * Crée une session Stripe Customer Portal.
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

        $planEnum = SubscriptionPlan::from($plan);
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
     * Traite un webhook Stripe.
     * Vérifie la signature avant de traiter l'événement.
     *
     * @throws \InvalidArgumentException si la signature est invalide
     */
    public function handleWebhook(string $payload, string $signature): void
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            $this->stripeWebhookSecret
        );

        $this->logger->info('[Stripe] Webhook reçu : ' . $event->type);

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
            $this->logger->error('[Stripe] checkout.session.completed sans organization_id ou plan');
            return;
        }

        $org = $this->em->getRepository(Organization::class)->find($orgId);
        if (!$org) {
            $this->logger->error('[Stripe] Organisation non trouvée : ' . $orgId);
            return;
        }

        $org->setPlan(SubscriptionPlan::from($plan));
        $org->setStripeCustomerId($session->customer);
        $this->em->flush();

        $this->logger->info(sprintf('[Stripe] Organisation %s passée au plan %s', $orgId, $plan));

        // Email de confirmation
        $firstUser = $org->getUsers()->first();
        $userEmail = $firstUser instanceof User ? $firstUser->getEmail() : null;
        if ($userEmail) {
            $email = (new Email())
                ->from($this->alertFromEmail)
                ->to($userEmail)
                ->subject('✅ Abonnement CannaSaaS activé — Plan ' . ucfirst($plan))
                ->text(sprintf(
                    "Votre abonnement CannaSaaS plan %s est maintenant actif.\n\n" .
                    "Vous pouvez gérer votre abonnement depuis votre espace facturation.\n\n" .
                    "Merci de faire confiance à CannaSaaS !",
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
            $this->logger->warning('[Stripe] Organisation non trouvée pour customer : ' . $customerId);
            return;
        }

        $org->setPlan(SubscriptionPlan::STARTER);
        $org->setLicenseStatus(LicenseStatus::SUSPENDED);
        $this->em->flush();

        $this->logger->info('[Stripe] Abonnement annulé pour organisation ' . $org->getId());
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
                ->subject('⚠️ Échec de paiement — Action requise')
                ->text(
                    "Le renouvellement de votre abonnement CannaSaaS a échoué.\n\n" .
                    "Veuillez mettre à jour votre moyen de paiement depuis votre espace facturation.\n" .
                    "Sans action de votre part sous 7 jours, votre accès sera suspendu."
                );
            $this->mailer->send($email);
        }
    }

    private function onPaymentSucceeded(Event $event): void
    {
        $this->logger->info('[Stripe] Paiement réussi : ' . $event->data->object->id);
    }

    private function getPriceId(SubscriptionPlan $plan): string
    {
        $priceId = match ($plan) {
            SubscriptionPlan::STARTER  => $this->priceStarter,
            SubscriptionPlan::PRO      => $this->pricePro,
            SubscriptionPlan::BUSINESS => $this->priceBusiness,
            default                    => throw new \InvalidArgumentException('Plan non géré : ' . $plan->value),
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
        return match ($priceId) {
            trim($this->priceStarter) => SubscriptionPlan::STARTER,
            trim($this->pricePro) => SubscriptionPlan::PRO,
            trim($this->priceBusiness) => SubscriptionPlan::BUSINESS,
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown Stripe price ID "%s" returned by subscription sync.',
                $priceId,
            )),
        };
    }
}
