<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class MercureService
{
    private const TOPIC_BASE = 'https://cultivatrace.com/tenants';
    private const TOKEN_TTL = 900;

    public function __construct(
        private HubInterface $hub,
        #[Autowire('%env(default:app.default_mercure_jwt_secret:MERCURE_JWT_SECRET)%')]
        private string $mercureJwtSecret,
    ) {
    }

    public function buildTenantSelector(string $tenantId): string
    {
        return sprintf('%s/%s/*', self::TOPIC_BASE, $tenantId);
    }

    public function buildRoomTopic(string $tenantId, string $roomId): string
    {
        return sprintf('%s/%s/rooms/%s', self::TOPIC_BASE, $tenantId, $roomId);
    }

    public function buildSensorTopic(string $tenantId, string $sensorId): string
    {
        return sprintf('%s/%s/sensors/%s', self::TOPIC_BASE, $tenantId, $sensorId);
    }

    /**
     * @param list<string> $topics
     * @param array<string, mixed> $payload
     */
    public function publishPrivateUpdate(array $topics, array $payload): void
    {
        $this->hub->publish(new Update(
            topics: $topics,
            data: json_encode($payload, JSON_THROW_ON_ERROR),
            private: true,
        ));
    }

    /**
     * @return array{token: string, expiresIn: int, expiresAt: string}
     */
    public function createSubscriptionToken(string $tenantId): array
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + self::TOKEN_TTL;

        $payload = [
            'mercure' => [
                'subscribe' => [$this->buildTenantSelector($tenantId)],
            ],
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        return [
            'token' => $this->encodeJwt($payload),
            'expiresIn' => self::TOKEN_TTL,
            'expiresAt' => (new \DateTimeImmutable(sprintf('@%d', $expiresAt)))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJwt(array $payload): string
    {
        if ($this->mercureJwtSecret === '') {
            throw new \LogicException('Mercure JWT secret must not be empty.');
        }

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', sprintf('%s.%s', $header, $encodedPayload), $this->mercureJwtSecret, true);

        return sprintf('%s.%s.%s', $header, $encodedPayload, $this->base64UrlEncode($signature));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
