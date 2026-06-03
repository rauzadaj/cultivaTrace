<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class SalesContactController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(default:app.sales.contact_email:SALES_CONTACT_EMAIL)%')]
        private readonly string $salesEmail,
    ) {}

    #[Route('/api/contact/sales', methods: ['POST'])]
    public function __invoke(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $data    = json_decode($request->getContent(), true) ?? [];
        $name    = trim((string) ($data['name']    ?? ''));
        $email   = trim((string) ($data['email']   ?? ''));
        $company = trim((string) ($data['company'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($name === '' || $email === '' || $message === '') {
            throw new \InvalidArgumentException('Name, email and message are required.');
        }

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        if (mb_strlen($message) > 5000) {
            throw new \InvalidArgumentException('Message must not exceed 5 000 characters.');
        }

        $orgName = $user?->getOrganization()?->getName() ?? $company;

        $mail = (new Email())
            ->from(new Address('noreply@cultivatrace.app', 'CultivaTrace'))
            ->to($this->salesEmail)
            ->replyTo(new Address($email, $name))
            ->subject(sprintf('[Enterprise] Contact — %s', $name))
            ->text(implode("\n", array_filter([
                "Name    : {$name}",
                "Email   : {$email}",
                $company ? "Company : {$company}" : null,
                $orgName && $orgName !== $company ? "Org     : {$orgName}" : null,
                '',
                $message,
            ])));

        $this->mailer->send($mail);

        return $this->json(['message' => 'Message sent.'], Response::HTTP_ACCEPTED);
    }
}
