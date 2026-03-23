<?php

namespace App\Tests\Infrastructure\Http\EventSubscriber;

use App\Infrastructure\Http\EventSubscriber\ApiExceptionSubscriber;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Driver\Exception as DriverException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionSubscriberTest extends TestCase
{
    public function testItMapsHttpExceptionsToTheirStatusCodeForApiRoutes(): void
    {
        $subscriber = new ApiExceptionSubscriber(new NullLogger());
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/crops/missing/transitions/harvest', 'POST');
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('Crop not found.'),
        );

        $subscriber->onKernelException($event);

        self::assertSame(Response::HTTP_NOT_FOUND, $event->getResponse()?->getStatusCode());
        self::assertStringContainsString('Crop not found.', (string) $event->getResponse()?->getContent());
    }

    public function testItMapsConstraintViolationsToConflictForApiRoutes(): void
    {
        $subscriber = new ApiExceptionSubscriber(new NullLogger());
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/genetics/CT-ALP', 'DELETE');
        $driverException = new class ('Foreign key violation') extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return '23503';
            }
        };
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new ForeignKeyConstraintViolationException($driverException, null),
        );

        $subscriber->onKernelException($event);

        self::assertSame(Response::HTTP_CONFLICT, $event->getResponse()?->getStatusCode());
    }
}
