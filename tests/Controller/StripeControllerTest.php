<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\StripeController;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use App\Service\BillingCheckoutService;
use App\Service\PlanLimitsService;
use App\Service\StripeService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class StripeControllerTest extends KernelTestCase
{
    public function testCheckoutDoesNotExposeStripeErrorDetails(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $stripe
            ->method('createCheckoutSession')
            ->willThrowException(new class('Stripe low-level detail') extends ApiErrorException {});
        $billingCheckoutService = new BillingCheckoutService($stripe);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                '[Stripe] Checkout session creation failed',
                self::arrayHasKey('error'),
            );

        $controller = $this->createController(
            $stripe,
            $this->createMock(PlanLimitsService::class),
            $billingCheckoutService,
            $logger,
        );

        $request = new Request([], [], [], [], [], [], json_encode(['plan' => 'starter'], JSON_THROW_ON_ERROR));
        $request->headers->set('CONTENT_TYPE', 'application/json');

        $response = $controller->checkout($request, $this->createUserWithOrganization());
        $payload = json_decode($response->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('Stripe checkout is currently unavailable for this plan.', $payload['error']);
        self::assertArrayNotHasKey('detail', $payload);
    }

    public function testCheckoutRoutesExistingCustomerToPortal(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $stripe
            ->expects(self::never())
            ->method('createCheckoutSession');
        $stripe
            ->expects(self::once())
            ->method('createPortalSession')
            ->willReturn('https://billing.stripe.test/portal');

        $billingCheckoutService = new BillingCheckoutService($stripe);

        $controller = $this->createController(
            $stripe,
            $this->createMock(PlanLimitsService::class),
            $billingCheckoutService,
            $this->createMock(LoggerInterface::class),
        );

        $request = new Request([], [], [], [], [], [], json_encode(['plan' => 'pro'], JSON_THROW_ON_ERROR));
        $request->headers->set('CONTENT_TYPE', 'application/json');

        $response = $controller->checkout($request, $this->createUserWithOrganization('cus_existing'));
        $payload = json_decode($response->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('https://billing.stripe.test/portal', $payload['checkoutUrl']);
    }

    public function testConfirmCheckoutDoesNotExposeStripeErrorDetails(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $stripe
            ->method('syncCheckoutSession')
            ->willThrowException(new class('Stripe confirmation detail') extends ApiErrorException {});
        $billingCheckoutService = new BillingCheckoutService($stripe);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                '[Stripe] Checkout confirmation failed',
                self::arrayHasKey('error'),
            );

        $controller = $this->createController(
            $stripe,
            $this->createMock(PlanLimitsService::class),
            $billingCheckoutService,
            $logger,
        );

        $request = new Request([], [], [], [], [], [], json_encode(['sessionId' => 'cs_test_123'], JSON_THROW_ON_ERROR));
        $request->headers->set('CONTENT_TYPE', 'application/json');

        $response = $controller->confirmCheckout($request, $this->createUserWithOrganization());
        $payload = json_decode($response->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('Unable to confirm Stripe checkout session.', $payload['error']);
        self::assertArrayNotHasKey('detail', $payload);
    }

    public function testCheckoutRejectsUserWithoutBillingWriteRole(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $billingCheckoutService = new BillingCheckoutService($stripe);

        $controller = $this->createController(
            $stripe,
            $this->createMock(PlanLimitsService::class),
            $billingCheckoutService,
            $this->createMock(LoggerInterface::class),
        );

        $request = new Request([], [], [], [], [], [], json_encode(['plan' => 'starter'], JSON_THROW_ON_ERROR));
        $request->headers->set('CONTENT_TYPE', 'application/json');

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);

        $controller->checkout($request, $this->createUserWithOrganization(roles: []));
    }

    public function testWebhookReturns401OnInvalidSignature(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $stripe
            ->method('handleWebhook')
            ->willThrowException(new \InvalidArgumentException('invalid signature'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                '[Stripe] Invalid webhook signature',
                self::arrayHasKey('error'),
            );

        $controller = $this->createController(
            $stripe,
            $this->createMock(PlanLimitsService::class),
            new BillingCheckoutService($stripe),
            $logger,
        );

        $request = new Request([], [], [], [], [], [], '{"type":"invoice.payment_failed"}');
        $request->headers->set('Stripe-Signature', 'invalid');

        $response = $controller->webhook($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testWebhookReturns500OnUnexpectedFailure(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $stripe
            ->method('handleWebhook')
            ->willThrowException(new \RuntimeException('unexpected failure'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                '[Stripe] Webhook handling failed',
                self::arrayHasKey('error'),
            );

        $controller = $this->createController(
            $stripe,
            $this->createMock(PlanLimitsService::class),
            new BillingCheckoutService($stripe),
            $logger,
        );

        $request = new Request([], [], [], [], [], [], '{"type":"invoice.payment_failed"}');
        $request->headers->set('Stripe-Signature', 't=1,v1=test');

        $response = $controller->webhook($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function testPortalDoesNotExposeStripeErrorDetails(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $stripe
            ->method('createPortalSession')
            ->willThrowException(new class('Stripe portal detail') extends ApiErrorException {});

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                '[Stripe] Portal session creation failed',
                self::arrayHasKey('error'),
            );

        $controller = $this->createController(
            $stripe,
            $this->createMock(PlanLimitsService::class),
            new BillingCheckoutService($stripe),
            $logger,
        );

        $response = $controller->portal($this->createUserWithOrganization('cus_123'));
        $payload = json_decode($response->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('Stripe billing portal is currently unavailable.', $payload['error']);
        self::assertArrayNotHasKey('detail', $payload);
    }

    public function testBillingStatusSyncsPlanFromStripeForExistingCustomer(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $stripe
            ->expects(self::once())
            ->method('syncOrganizationSubscription')
            ->willReturnCallback(static function (Organization $organization): SubscriptionPlan {
                $organization->setPlan(SubscriptionPlan::BUSINESS);

                return SubscriptionPlan::BUSINESS;
            });

        $controller = $this->createController(
            $stripe,
            $this->createMock(PlanLimitsService::class),
            new BillingCheckoutService($stripe),
            $this->createMock(LoggerInterface::class),
        );

        $response = $controller->billingStatus($this->createUserWithOrganization('cus_123'));
        $payload = json_decode($response->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('business', $payload['plan']);
        self::assertTrue($payload['hasActiveSubscription']);
    }

    private function createUserWithOrganization(?string $stripeCustomerId = null, array $roles = ['ROLE_ORG_ADMIN']): User
    {
        $organization = new Organization();
        $organization->setName('Org Stripe');
        $organization->setPlan(SubscriptionPlan::STARTER);
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $organization->setStripeCustomerId($stripeCustomerId);

        $user = new User();
        $user->setEmail('stripe@test.local');
        $user->setOrganization($organization);
        $user->setRoles($roles);

        return $user;
    }

    private function createController(
        StripeService $stripe,
        PlanLimitsService $planLimits,
        BillingCheckoutService $billingCheckoutService,
        LoggerInterface $logger,
    ): StripeController {
        self::bootKernel();

        $controller = new StripeController(
            $stripe,
            $planLimits,
            $billingCheckoutService,
            $logger,
            'http://localhost:5173',
        );
        $controller->setContainer(static::getContainer());

        return $controller;
    }
}
