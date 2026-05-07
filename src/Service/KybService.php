<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\HealthCanadaLicensedProducer;
use App\Entity\LicenseDocument;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * KybService — vérification de licence (Know Your Business).
 *
 * Stratégie de vérification automatique :
 *   1. Canada (Health Canada CTS) → vérification via registre public
 *   2. USA (METRC) → vérification via API METRC (clé requise)
 *   3. Allemagne (BfArM) → vérification manuelle (API non publique)
 *   4. Fallback → validation manuelle par admin CannaSaaS
 *
 * En dev : toutes les vérifications retournent "verified" après 5s
 * (simulation de l'appel API).
 */
class KybService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $appEnv = 'dev',
        private readonly string $metrcApiKey = '',
        private readonly string $alertFromEmail = '',
        private readonly string $adminReviewEmail = '',
        private readonly string $adminReviewUrl = '',
        private readonly string $appUrl = '',
        private readonly string $supportEmail = '',
        private readonly bool $devSimulationEnabled = false,
    ) {}

    /**
     * Lance la vérification automatique d'une licence.
     * Appelé après l'upload du document.
     *
     * @return array{verified: bool, method: string, reason: string|null}
     */
    public function verify(LicenseDocument $license): array
    {
        if ($this->devSimulationEnabled && $license->getLicenseType() === 'ctls_dev') {
            return $this->simulateVerification($license);
        }

        return match ($license->getLicenseType()) {
            'health_canada' => $this->verifyHealthCanada($license),
            'metrc_usa'     => $this->verifyMetrc($license),
            'bfarm_de'      => $this->fallbackManual($license, 'BfArM ne dispose pas d\'API publique'),
            'ansm_fr'       => $this->fallbackManual($license, 'ANSM nécessite une revue manuelle.'),
            'ctls_dev'      => $this->fallbackManual($license, 'Dev-only CTLS simulation is disabled in this environment.'),
            default         => $this->fallbackManual($license, 'Type de licence non reconnu'),
        };
    }

    public function isDevSimulationEnabled(): bool
    {
        return $this->devSimulationEnabled;
    }

    /**
     * Simulation pour le dev — approuve automatiquement après vérification factice.
     */
    private function simulateVerification(LicenseDocument $license): array
    {
        $this->logger->info('[KYB DEV] Simulation de vérification', [
            'license' => $this->maskLicense($license->getLicenseNumber()),
            'type'    => $license->getLicenseType(),
        ]);

        // Simuler un délai d'appel API
        sleep(1);

        if ($license->getLicenseType() === 'ctls_dev') {
            if (!preg_match('/^TEST-CTLS-[A-Z0-9-]{4,}$/', strtoupper($license->getLicenseNumber()))) {
                return [
                    'verified' => false,
                    'method'   => 'simulated',
                    'reason'   => 'Le format de licence CTLS fictive doit commencer par TEST-CTLS-',
                ];
            }

            return [
                'verified'   => true,
                'method'     => 'simulated_ctls_dev',
                'expiresAt'  => (new \DateTimeImmutable('+2 years'))->format('Y-m-d'),
                'reason'     => null,
            ];
        }

        // En dev : toujours approuver (sauf si le numéro contient "INVALID")
        if (str_contains(strtoupper($license->getLicenseNumber()), 'INVALID')) {
            return [
                'verified' => false,
                'method'   => 'simulated',
                'reason'   => 'Numéro de licence invalide (simulation dev)',
            ];
        }

        return [
            'verified'   => true,
            'method'     => 'simulated',
            'expiresAt'  => (new \DateTimeImmutable('+2 years'))->format('Y-m-d'),
            'reason'     => null,
        ];
    }

    /**
     * Vérification Health Canada via le registre local synchronisé quotidiennement.
     *
     * Le registre est peuplé par SyncHealthCanadaRegistryCommand (app:sync-health-canada-registry)
     * qui télécharge le CSV officiel de Health Canada :
     *   https://health-products.canada.ca/api/dataset/95c29d8f-3688-4a37-aca0-1a50af7b1c86
     *
     * Si le registre est vide (première installation), fallback sur revue manuelle.
     */
    private function verifyHealthCanada(LicenseDocument $license): array
    {
        $licenseNumber = strtoupper(trim($license->getLicenseNumber()));

        // Validate format before lookup — accept single or compound prefixes (e.g. LP-xxxx or HC-LP-xxxx)
        if (!preg_match('/^[A-Z]{1,3}(-[A-Z]{1,3})?-[A-Z0-9]{3,}$/i', $licenseNumber)) {
            $this->logger->warning('[KYB] Format de licence Health Canada invalide', [
                'license' => $this->maskLicense($licenseNumber),
            ]);

            return [
                'verified' => false,
                'method'   => 'format_rejected',
                'reason'   => 'Format de numéro de licence invalide. Formats acceptés : LIC-XXXXX, LP-XXXXX, MC-XXXXX, MR-XXXXX.',
            ];
        }

        // Lookup in local registry (populated by app:sync-health-canada-registry)
        $producer = $this->em
            ->getRepository(HealthCanadaLicensedProducer::class)
            ->findOneBy(['licenseNumber' => $licenseNumber]);

        if ($producer === null) {
            // Registry may be empty on first install — fallback to manual review
            $this->logger->info('[KYB] Health Canada — licence non trouvée dans le registre local', [
                'license'    => $this->maskLicense($licenseNumber),
                'suggestion' => 'Lancez app:sync-health-canada-registry pour peupler le registre.',
            ]);

            return $this->fallbackManual($license, 'Licence non trouvée dans le registre local Health Canada. Revue manuelle requise.');
        }

        if (!$producer->isActive()) {
            $this->logger->info('[KYB] Health Canada — licence inactive', [
                'license' => $this->maskLicense($licenseNumber),
                'status'  => $producer->getStatus(),
            ]);

            return [
                'verified'  => false,
                'method'    => 'auto_health_canada',
                'expiresAt' => $producer->getExpiresAt()?->format('Y-m-d'),
                'reason'    => sprintf(
                    'La licence "%s" est enregistrée avec le statut "%s" dans le registre Health Canada.',
                    $producer->getCompanyName(),
                    $producer->getStatus(),
                ),
            ];
        }

        $this->logger->info('[KYB] Health Canada — licence vérifiée automatiquement', [
            'license' => $this->maskLicense($licenseNumber),
            'company' => $producer->getCompanyName(),
        ]);

        return [
            'verified'  => true,
            'method'    => 'auto_health_canada',
            'expiresAt' => $producer->getExpiresAt()?->format('Y-m-d'),
            'reason'    => null,
        ];
    }

    /**
     * Vérification METRC — API officielle des États US.
     */
    private function verifyMetrc(LicenseDocument $license): array
    {
        if (empty($this->metrcApiKey)) {
            return $this->fallbackManual($license, 'Clé API METRC non configurée');
        }

        try {
            // METRC API endpoint (varie selon l'État)
            // https://api-{state}.metrc.com/v1/licenses/{licenseNumber}
            $stateCode = $this->extractStateCode($license->getLicenseNumber());
            $endpoint  = "https://api-{$stateCode}.metrc.com/v1/licenses/{$license->getLicenseNumber()}";

            $response = $this->httpClient->request('GET', $endpoint, [
                'auth_basic' => [$this->metrcApiKey, ''],
                'timeout'    => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return [
                    'verified'  => true,
                    'method'    => 'auto_metrc',
                    'expiresAt' => $data['ExpirationDate'] ?? null,
                    'reason'    => null,
                ];
            }

            return $this->fallbackManual($license, 'Licence non trouvée dans METRC');

        } catch (\Throwable $e) {
            $this->logger->error('[KYB] Erreur METRC', [
                'error'   => $e->getMessage(),
                'license' => $this->maskLicense($license->getLicenseNumber()),
            ]);
            return $this->fallbackManual($license, 'Erreur API METRC: ' . $e->getMessage());
        }
    }

    /**
     * Fallback manuel — notifie l'admin CannaSaaS par email.
     */
    private function fallbackManual(LicenseDocument $license, string $reason): array
    {
        $this->logger->info('[KYB] Fallback manuel', [
            'license' => $this->maskLicense($license->getLicenseNumber()),
            'reason'  => $reason,
        ]);

        $this->notifyAdminForManualReview($license, $reason);

        return [
            'verified' => false,
            'method'   => 'manual',
            'reviewed' => false,
            'reason'   => $reason,
        ];
    }

    /**
     * Applique le résultat de la vérification sur le LicenseDocument
     * et met à jour le statut de l'Organization.
     */
    public function applyVerificationResult(
        LicenseDocument $license,
        array $result,
    ): void {
        $org = $license->getOrganization();
        $reviewed = (bool) ($result['reviewed'] ?? false);

        if ($result['verified']) {
            $license->setStatus('active');
            $license->setVerifiedAt(new \DateTimeImmutable());
            $license->setVerificationMethod($result['method']);
            $license->setRejectionReason(null);

            if (isset($result['expiresAt'])) {
                $license->setLicenseExpiresAt(new \DateTimeImmutable($result['expiresAt']));
                $org->setLicenseExpiresAt(new \DateTimeImmutable($result['expiresAt']));
            }

            $org->setLicenseStatus(LicenseStatus::ACTIVE);
            $this->notifyUserActivated($license);

        } else {
            $isPendingManualReview = $result['method'] === 'manual'
                && !$reviewed
                && $license->getStatus() === LicenseStatus::PENDING->value
                && $org->getLicenseStatus() === LicenseStatus::PENDING;

            if ($isPendingManualReview) {
                $license->setStatus('pending');
                $org->setLicenseStatus(LicenseStatus::PENDING);
            } else {
                $license->setStatus('rejected');
                $license->setVerificationMethod($result['method']);
                $license->setRejectionReason($result['reason']);
                $org->setLicenseStatus(LicenseStatus::REJECTED);
                $this->notifyUserRejected($license);
            }
        }

        $this->em->flush();
    }

    private function notifyAdminForManualReview(LicenseDocument $license, string $reason): void
    {
        if ($this->adminReviewEmail === '' || $this->alertFromEmail === '') {
            $this->logger->warning('[KYB] Manual review notification skipped because KYB email configuration is incomplete.', [
                'licenseId' => (string) $license->getId(),
            ]);

            return;
        }

        try {
            $reviewInstruction = $this->adminReviewUrl !== ''
                ? sprintf("Traitez cette demande : %s", $this->adminReviewUrl)
                : 'Traitez cette demande via le backoffice KYB configuré pour cet environnement.';

            $email = (new Email())
                ->from($this->alertFromEmail)
                ->to($this->adminReviewEmail)
                ->subject('[KYB] Vérification manuelle requise — ' . $license->getLicenseNumber())
                ->text(sprintf(
                    "Une vérification manuelle est requise.\n\n" .
                    "Organisation : %s\n" .
                    "Numéro de licence : %s\n" .
                    "Type : %s\n" .
                    "Raison : %s\n" .
                    "Soumis le : %s\n\n" .
                    "%s",
                    $license->getOrganization()->getName(),
                    $license->getLicenseNumber(),
                    $license->getLicenseType(),
                    $reason,
                    $license->getSubmittedAt()->format('d/m/Y H:i'),
                    $reviewInstruction,
                ));
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('[KYB] Erreur envoi email admin', ['error' => $e->getMessage()]);
        }
    }

    private function notifyUserActivated(LicenseDocument $license): void
    {
        if ($this->alertFromEmail === '' || $this->appUrl === '') {
            $this->logger->warning('[KYB] Activation email skipped because KYB user notification configuration is incomplete.', [
                'licenseId' => (string) $license->getId(),
            ]);

            return;
        }

        try {
            $firstUser = $license->getOrganization()->getUsers()->first();
            $userEmail = $firstUser instanceof User ? $firstUser->getEmail() : null;
            if (!$userEmail) return;

            $email = (new Email())
                ->from($this->alertFromEmail)
                ->to($userEmail)
                ->subject('✅ Votre licence CannaSaaS a été validée')
                ->text(sprintf(
                    "Bonne nouvelle !\n\n" .
                    "Votre licence %s a été vérifiée et validée.\n" .
                    "Vous avez maintenant accès à toutes les fonctionnalités CannaSaaS.\n\n" .
                    "Connectez-vous sur %s",
                    rtrim($this->appUrl, '/'),
                    $license->getLicenseNumber()
                ));
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('[KYB] Erreur email activation', ['error' => $e->getMessage()]);
        }
    }

    private function notifyUserRejected(LicenseDocument $license): void
    {
        if ($this->alertFromEmail === '' || $this->supportEmail === '') {
            $this->logger->warning('[KYB] Rejection email skipped because KYB user notification configuration is incomplete.', [
                'licenseId' => (string) $license->getId(),
            ]);

            return;
        }

        try {
            $firstUser = $license->getOrganization()->getUsers()->first();
            $userEmail = $firstUser instanceof User ? $firstUser->getEmail() : null;
            if (!$userEmail) return;

            $email = (new Email())
                ->from($this->alertFromEmail)
                ->to($userEmail)
                ->subject('❌ Vérification de licence CannaSaaS — Action requise')
                ->text(sprintf(
                    "Votre demande de vérification de licence n'a pas pu être validée.\n\n" .
                    "Licence soumise : %s\n" .
                    "Raison : %s\n\n" .
                    "Veuillez vérifier votre numéro de licence et soumettre à nouveau.\n" .
                    "Si le problème persiste, contactez %s",
                    $license->getLicenseNumber(),
                    $license->getRejectionReason() ?? 'Licence non reconnue',
                    $this->supportEmail,
                ));
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('[KYB] Erreur email rejet', ['error' => $e->getMessage()]);
        }
    }

    private function extractStateCode(string $licenseNumber): string
    {
        // Format METRC typique : CO-LIC-12345 → co
        $parts = explode('-', strtolower($licenseNumber));
        return $parts[0] ?? 'co';
    }

    private function maskLicense(string $licenseNumber): string
    {
        $visible = min(4, strlen($licenseNumber));
        return substr($licenseNumber, 0, $visible) . str_repeat('*', max(0, strlen($licenseNumber) - $visible));
    }
}
