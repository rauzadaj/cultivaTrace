<?php

namespace App\Tests\Infrastructure\Http\EventSubscriber;

use App\Infrastructure\Http\EventSubscriber\ApiExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionSubscriberTest extends TestCase
{
    public function testItMapsHttpExceptionsToTheirStatusCodeForApiRoutes(): void
    {
        $subscriber = new ApiExceptionSubscriber();
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
}
