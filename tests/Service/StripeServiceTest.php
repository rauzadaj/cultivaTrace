<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\SubscriptionPlan;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

final class StripeServiceTest extends TestCase
{
    public function testItRejectsAProductIdUsedAsAPriceId(): void
    {
        $service = new StripeService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class),
            'sk_test_123',
            'whsec_123',
            'prod_wrong',
            'price_ok',
            'price_ok_scale',
            'price_ok_enterprise',
        );

        $method = new \ReflectionMethod($service, 'getPriceId');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a Stripe price_ identifier');

        $method->invoke($service, SubscriptionPlan::GROWTH);
    }

    public function testResolvePlanFromPriceIdResolvesLegacyStarterToGrowth(): void
    {
        $service = new StripeService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class),
            'sk_test_123',
            'whsec_123',
            'price_growth',
            'price_pro',
            'price_scale',
            'price_enterprise',
            'billing@test.local',
            'price_legacy_starter',
            'price_legacy_business',
        );

        $method = new \ReflectionMethod($service, 'resolvePlanFromPriceId');
        $method->setAccessible(true);

        self::assertSame(SubscriptionPlan::GROWTH, $method->invoke($service, 'price_legacy_starter'));
        self::assertSame(SubscriptionPlan::SCALE,  $method->invoke($service, 'price_legacy_business'));
    }

    public function testResolvePlanFromPriceIdThrowsOnTrulyUnknownId(): void
    {
        $service = new StripeService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class),
            'sk_test_123',
            'whsec_123',
            'price_growth',
            'price_pro',
            'price_scale',
            'price_enterprise',
        );

        $method = new \ReflectionMethod($service, 'resolvePlanFromPriceId');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Stripe price ID');

        $method->invoke($service, 'price_completely_unknown');
    }
}
