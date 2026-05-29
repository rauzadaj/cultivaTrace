<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Service\Auth\OrganizationInvitationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AcceptOrganizationInvitationController extends AbstractController
{
    public function __construct(
        private readonly OrganizationInvitationService $invitationService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/api/register/invitation/accept', methods: ['POST'])]
    public function __invoke(
        #[Autowire(service: 'limiter.api_register_invitation')] RateLimiterFactory $invitationLimiter,
        Request $request,
    ): JsonResponse {
        // Throttle by client IP: the acceptance endpoint takes an invitation
        // token + password, so without a limit it is open to token enumeration
        // and password brute-forcing.
        $clientIp = $request->getClientIp() ?? 'unknown';
        if (false === $invitationLimiter->create($clientIp)->consume(1)->isAccepted()) {
            return $this->json(
                ['error' => 'Too many invitation attempts. Please retry later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        try {
            $user = $this->invitationService->acceptInvitation($token, $password);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'status' => 'accepted',
            'userId' => (string) $user->getId(),
        ], Response::HTTP_CREATED);
    }
}
