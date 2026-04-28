<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Service\Auth\OrganizationInvitationService;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AcceptOrganizationInvitationController
{
    public function __construct(
        private OrganizationInvitationService $invitationService,
    ) {
    }

    #[Route('/api/register/invitation/accept', name: 'api_register_invitation_accept', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            $payload = [];
        }

        $token = trim((string) ($payload['token'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($token === '' || $password === '') {
            return new JsonResponse([
                'error' => 'Invitation token and password are required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $this->invitationService->acceptInvitation($token, $password);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => 'Invitation accepted successfully.',
            'email' => $user->getEmail(),
        ], Response::HTTP_CREATED);
    }
}
