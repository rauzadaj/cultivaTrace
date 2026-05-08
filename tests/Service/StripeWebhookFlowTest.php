<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Validates the SYNC-05 webhook flow end-to-end (unit level):
 *   checkout.session.completed → plan updated
 *   customer.subscription.deleted → plan reverted + SUSPENDED
 *   invoice.payment_failed → error logged
 *   invalid signature → InvalidArgumentException
 *
 * Uses Stripe\Webhook::generateTestHeaderString() to produce valid HMAC
 * signatures so signature verification runs for real.
 */
final class StripeWebhookFlowTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_stripe_webhook_flow_secret_for_tests';

    private function makeService(
        EntityManagerInterface $em,
        ?MailerInterface $mailer = null,
        ?LoggerInterface $logger = null,
    ): StripeService {
        return new StripeService(
            $em,
            $mailer ?? $this->createMock(MailerInterface::class),
            $logger ?? $this->createMock(LoggerInterface::class),
            'sk_test_dummy',
            self::WEBHOOK_SECRET,
            'price_starter',
            'price_pro',
            'price_business',
            'price_enterprise',
            'billing@test.local',
        );
    }

    private function signedRequest(string $json): array
    {
        // Replicate Stripe's v1 HMAC-SHA256 header format (same as WebhookSignature::computeSignature).
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$json}";
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);
        $header = "t={$timestamp},v1={$signature}";
        return [$json, $header];
    }

    // ── checkout.session.completed ────────────────────────────────────────

    public function testCheckoutSessionCompletedUpdatesPlan(): void
    {
        $org = new Organization();
        $org->setName('Test Org');
        $org->setPlan(SubscriptionPlan::STARTER);
        $org->setLicenseStatus(LicenseStatus::ACTIVE);

        $user = new User();
        $user->setEmail('owner@test.local');
        $user->setOrganization($org);

        $orgId = '01930000-0000-0000-0000-000000000001';

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with($orgId)->willReturn($org);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects(self::once())->method('flush');

        $session = [
            'id'               => 'cs_test_abc123',
            'object'           => 'checkout.session',
            'payment_status'   => 'paid',
            'status'           => 'complete',
            'customer'         => 'cus_test_123',
            'client_reference_id' => $orgId,
            'metadata'         => ['organization_id' => $orgId, 'plan' => 'pro'],
        ];

        $payload = json_encode([
            'id'      => 'evt_test_checkout_completed',
            'object'  => 'event',
            'type'    => 'checkout.session.completed',
            'data'    => ['object' => $session],
        ], JSON_THROW_ON_ERROR);

        [$json, $sig] = $this->signedRequest($payload);

        $this->makeService($em)->handleWebhook($json, $sig);

        self::assertSame(SubscriptionPlan::PRO, $org->getPlan());
        self::assertSame('cus_test_123', $org->getStripeCustomerId());
    }

    public function testCheckoutSessionCompletedWithMissingOrgIdLogsError(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('error');

        $session = [
            'id'       => 'cs_test_no_org',
            'object'   => 'checkout.session',
            'status'   => 'complete',
            'customer' => 'cus_xyz',
            'metadata' => [],
        ];

        $payload = json_encode([
            'id'   => 'evt_test_no_org',
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session],
        ], JSON_THROW_ON_ERROR);

        [$json, $sig] = $this->signedRequest($payload);

        $this->makeService($em, logger: $logger)->handleWebhook($json, $sig);
    }

    // ── customer.subscription.deleted ────────────────────────────────────

    public function testSubscriptionDeletedRevertsToStarterAndSuspends(): void
    {
        $org = new Organization();
        $org->setName('Test Org');
        $org->setPlan(SubscriptionPlan::PRO);
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $org->setStripeCustomerId('cus_sub_deleted');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->with(['stripeCustomerId' => 'cus_sub_deleted'])->willReturn($org);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects(self::once())->method('flush');

        $subscription = [
            'id'       => 'sub_test_deleted',
            'object'   => 'subscription',
            'customer' => 'cus_sub_deleted',
            'status'   => 'canceled',
        ];

        $payload = json_encode([
            'id'   => 'evt_test_sub_deleted',
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => $subscription],
        ], JSON_THROW_ON_ERROR);

        [$json, $sig] = $this->signedRequest($payload);

        $this->makeService($em)->handleWebhook($json, $sig);

        self::assertSame(SubscriptionPlan::STARTER, $org->getPlan());
        self::assertSame(LicenseStatus::SUSPENDED, $org->getLicenseStatus());
    }

    // ── invoice.payment_failed ────────────────────────────────────────────

    public function testPaymentFailedSendsAlertEmailToOrgOwner(): void
    {
        $org = new Organization();
        $org->setName('Test Org');
        $org->setPlan(SubscriptionPlan::PRO);
        $org->setStripeCustomerId('cus_payment_failed');

        $user = new User();
        $user->setEmail('owner@test.local');
        $user->setOrganization($org);
        // Populate the org's users collection (no addUser method, so use reflection).
        $prop = new \ReflectionProperty(Organization::class, 'users');
        $prop->setAccessible(true);
        $prop->getValue($org)->add($user);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->with(['stripeCustomerId' => 'cus_payment_failed'])->willReturn($org);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $invoice = [
            'id'       => 'in_test_failed',
            'object'   => 'invoice',
            'customer' => 'cus_payment_failed',
        ];

        $payload = json_encode([
            'id'   => 'evt_test_payment_failed',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => $invoice],
        ], JSON_THROW_ON_ERROR);

        [$json, $sig] = $this->signedRequest($payload);

        $this->makeService($em, mailer: $mailer)->handleWebhook($json, $sig);
    }

    // ── invoice.payment_succeeded ─────────────────────────────────────────

    public function testPaymentSucceededWithUnknownCustomerLogsInfoAndDoesNotFlush(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects(self::never())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('info');

        $invoice = ['id' => 'in_unknown', 'object' => 'invoice', 'customer' => 'cus_no_match'];

        $payload = json_encode([
            'id'   => 'evt_payment_succeeded_unknown',
            'type' => 'invoice.payment_succeeded',
            'data' => ['object' => $invoice],
        ], JSON_THROW_ON_ERROR);

        [$json, $sig] = $this->signedRequest($payload);

        $this->makeService($em, logger: $logger)->handleWebhook($json, $sig);
    }

    // ── webhook redelivery idempotence ────────────────────────────────────

    public function testSameCheckoutEventDeliveredTwiceProducesSameState(): void
    {
        $org = new Organization();
        $org->setName('Idempotent Org');
        $org->setPlan(SubscriptionPlan::STARTER);
        $org->setLicenseStatus(LicenseStatus::ACTIVE);

        $orgId = '01930000-0000-0000-0000-000000000002';

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with($orgId)->willReturn($org);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $session = [
            'id'                  => 'cs_test_idempotent',
            'object'              => 'checkout.session',
            'payment_status'      => 'paid',
            'status'              => 'complete',
            'customer'            => 'cus_idempotent',
            'client_reference_id' => $orgId,
            'metadata'            => ['organization_id' => $orgId, 'plan' => 'pro'],
        ];

        $payload = json_encode([
            'id'   => 'evt_idempotent_checkout',
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session],
        ], JSON_THROW_ON_ERROR);

        [$json, $sig] = $this->signedRequest($payload);

        $service = $this->makeService($em);
        $service->handleWebhook($json, $sig);
        $service->handleWebhook($json, $sig); // redelivery

        self::assertSame(SubscriptionPlan::PRO, $org->getPlan());
        self::assertSame('cus_idempotent', $org->getStripeCustomerId());
    }

    // ── signature validation ──────────────────────────────────────────────

    public function testInvalidSignatureThrows(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        // Stripe throws SignatureVerificationException on bad sig (extends ApiErrorException).
        $this->expectException(\Stripe\Exception\SignatureVerificationException::class);

        $this->makeService($em)->handleWebhook('{}', 'invalid_signature');
    }
}
