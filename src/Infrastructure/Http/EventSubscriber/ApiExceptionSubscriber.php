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
use Psr\Log\LoggerInterface;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const JSON_ENCODING_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

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

        if (Response::HTTP_INTERNAL_SERVER_ERROR === $status) {
            $this->logger->error(sprintf(
                'Unhandled API exception on %s: %s: %s',
                $request->getPathInfo(),
                $exception::class,
                $exception->getMessage(),
            ));
        }

        $response = new JsonResponse(null, $status);
        $response->setEncodingOptions(self::JSON_ENCODING_OPTIONS);
        $response->setData([
            'title' => Response::$statusTexts[$status] ?? 'Application Error',
            'detail' => Response::HTTP_INTERNAL_SERVER_ERROR === $status ? 'An unexpected error occurred.' : $exception->getMessage(),
            'status' => $status,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $event->setResponse($response);
    }
}
