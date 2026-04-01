<?php

namespace App\Tests\Infrastructure\Http\EventSubscriber;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersSubscriberTest extends WebTestCase
{
    public function testSecurityHeadersAreAddedOnSecureRequests(): void
    {
        $client = static::createClient(server: [
            'HTTPS' => 'on',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $client->request('GET', '/api/health');
        $response = $client->getResponse();

        self::assertTrue($response->isSuccessful(), $response->getContent());
        self::assertSame("default-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:; connect-src 'self' https: http: ws: wss:; font-src 'self' data: https:; form-action 'self'", $response->headers->get('Content-Security-Policy'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertSame('camera=(), geolocation=(), microphone=(), payment=(), usb=()', $response->headers->get('Permissions-Policy'));
        self::assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    public function testHstsIsNotAddedOnInsecureRequests(): void
    {
        $client = static::createClient(server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $client->request('GET', '/api/health');
        $response = $client->getResponse();

        self::assertTrue($response->isSuccessful(), $response->getContent());
        self::assertFalse($response->headers->has('Strict-Transport-Security'));
    }
}
