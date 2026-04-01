<?php

namespace App\Infrastructure\Http\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private const CONTENT_SECURITY_POLICY = "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'; form-action 'none'";
    private const PERMISSIONS_POLICY = 'camera=(), geolocation=(), microphone=(), payment=(), usb=()';
    private const REFERRER_POLICY = 'strict-origin-when-cross-origin';
    private const STRICT_TRANSPORT_SECURITY = 'max-age=31536000; includeSubDomains';
    private const X_CONTENT_TYPE_OPTIONS = 'nosniff';
    private const X_FRAME_OPTIONS = 'DENY';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $headers = $response->headers;

        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }

        if (!$headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', self::X_FRAME_OPTIONS);
        }

        if (!$headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', self::X_CONTENT_TYPE_OPTIONS);
        }

        if (!$headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', self::REFERRER_POLICY);
        }

        if (!$headers->has('Permissions-Policy')) {
            $headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);
        }

        if ($event->getRequest()->isSecure() && !$headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', self::STRICT_TRANSPORT_SECURITY);
        }
    }
}
