<?php

namespace App\Infrastructure\Http\EventSubscriber;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof UniqueConstraintViolationException, $exception instanceof ForeignKeyConstraintViolationException => Response::HTTP_CONFLICT,
            $exception instanceof InvalidArgumentException => Response::HTTP_BAD_REQUEST,
            $exception instanceof DomainException => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        $event->setResponse(new JsonResponse([
            'title' => Response::$statusTexts[$status] ?? 'Application Error',
            'detail' => Response::HTTP_INTERNAL_SERVER_ERROR === $status ? 'An unexpected error occurred.' : $exception->getMessage(),
            'status' => $status,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $status));
    }
}
