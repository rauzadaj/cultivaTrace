<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\EventSubscriber;

use App\Service\PlanLimitExceededException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const JSON_ENCODING_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    private const PROBLEM_JSON = 'application/problem+json';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

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
            $exception instanceof HttpExceptionInterface                      => $exception->getStatusCode(),
            $exception instanceof UniqueConstraintViolationException,
            $exception instanceof ForeignKeyConstraintViolationException      => Response::HTTP_CONFLICT,
            $exception instanceof InvalidArgumentException                    => Response::HTTP_BAD_REQUEST,
            $exception instanceof DomainException                             => Response::HTTP_UNPROCESSABLE_ENTITY,
            default                                                           => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        if (Response::HTTP_INTERNAL_SERVER_ERROR === $status) {
            $this->logger->error(sprintf(
                'Unhandled API exception on %s: %s: %s',
                $request->getPathInfo(),
                $exception::class,
                $exception->getMessage(),
            ));
        }

        $body = [
            'title'     => Response::$statusTexts[$status] ?? 'Application Error',
            'detail'    => Response::HTTP_INTERNAL_SERVER_ERROR === $status
                ? 'An unexpected error occurred.'
                : $exception->getMessage(),
            'status'    => $status,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        // For plan-limit errors (402), inline the structured data so the client
        // does not have to double-parse the detail string.
        $cause = $exception->getPrevious();
        if ($status === Response::HTTP_PAYMENT_REQUIRED && $cause instanceof PlanLimitExceededException) {
            $body['detail'] = $cause->getMessage();
            $body['planLimit'] = [
                'limitType'  => $cause->limitType,
                'current'    => $cause->current,
                'max'        => $cause->max === PHP_INT_MAX ? null : $cause->max,
                'upgradeTo'  => $cause->upgradeTo->value,
                'upgradeUrl' => '/billing',
            ];
        }

        $response = new JsonResponse($body, $status, ['Content-Type' => self::PROBLEM_JSON]);
        $response->setEncodingOptions(self::JSON_ENCODING_OPTIONS);

        $event->setResponse($response);
    }
}
