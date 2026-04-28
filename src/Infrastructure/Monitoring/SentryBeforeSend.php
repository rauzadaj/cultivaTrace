<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class SentryBeforeSend
{
    public function __invoke(Event $event, EventHint $hint): ?Event
    {
        // Ne pas envoyer les 404 et 401 — bruit inutile
        $exception = $hint->exception;
        if ($exception instanceof NotFoundHttpException) {
            return null;
        }
        if ($exception instanceof UnauthorizedHttpException) {
            return null;
        }

        return $event;
    }
}
