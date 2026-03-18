<?php

namespace App\Controller;

use App\Entity\Plant;
use App\Repository\PlantEventRepository;
use App\Service\HashChainService;
use Doctrine\ORM\EntityManagerInterface;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/plants/{id}/report
 *
 * Génère le rapport PDF seed-to-harvest via GotenbergBundle.
 *
 * Prérequis :
 *   composer require sensiolabs/gotenberg-bundle
 *   docker-compose : service gotenberg (voir SETUP.md)
 *
 * SYNC-03 : ce rapport doit être validé par un contact BfArM
 * ou Health Canada avant le lancement beta.
 */
#[Route('/api/plants/{id}/report', methods: ['GET'])]
class PlantReportController extends AbstractController
{
    public function __construct(
        private readonly GotenbergPdfInterface $gotenberg,
        private readonly PlantEventRepository $eventRepo,
        private readonly HashChainService $hashChain,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(Plant $plant): Response
    {
        $events       = $this->eventRepo->findByPlantOrderedAsc($plant->getId());
        $inputRecords = $this->em->getRepository(\App\Entity\InputRecord::class)
            ->findBy(['plant' => $plant], ['appliedAt' => 'ASC']);
        $harvestRecord = $plant->getHarvestRecord();
        $integrity     = $this->hashChain->verify($plant->getId());

        try {
            $pdf = $this->gotenberg
                ->html()
                ->content('pdf/plant_report.html.twig', [
                    'plant'         => $plant,
                    'events'        => $events,
                    'inputRecords'  => $inputRecords,
                    'harvestRecord' => $harvestRecord,
                    'integrity'     => $integrity,
                    'generatedAt'   => new \DateTimeImmutable(),
                    'totalEvents'   => count($events),
                ])
                ->paperStandardSize(\Sensiolabs\GotenbergBundle\Enumeration\PaperSize::A4)
                ->margins(
                    top: 1,
                    bottom: 1,
                    left: 1,
                    right: 1,
                    unit: \Sensiolabs\GotenbergBundle\Enumeration\Unit::Centimeters,
                )
                ->generate();
        } catch (\Throwable $exception) {
            $payload = [
                'error' => 'PDF generation service is unavailable.',
            ];

            if (isset($this->container)
                && $this->container->hasParameter('kernel.environment')
                && $this->container->getParameter('kernel.environment') === 'test') {
                $payload['details'] = $exception->getMessage();
            }

            return new JsonResponse($payload, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $filename = sprintf(
            'rapport-plant-%s-%s.pdf',
            substr((string) $plant->getId(), 0, 8),
            date('Y-m-d')
        );

        $response = $pdf->setDisposition('attachment')->stream();
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
