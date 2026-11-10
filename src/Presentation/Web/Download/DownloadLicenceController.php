<?php

declare(strict_types=1);

namespace App\Presentation\Web\Download;

use App\Application\Sales\SalesDocuments;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
#[Route('/download/{orderId}/licence', name: 'download_licence', requirements: ['orderId' => Requirement::ULID], methods: ['GET'])]
final readonly class DownloadLicenceController
{
    public function __construct(
        private DownloadGate $gate,
        private SalesDocuments $documents,
    ) {
    }

    public function __invoke(Request $request, string $orderId): Response
    {
        $order = $this->gate->open($request, $orderId);

        return new Response($this->documents->licence($order), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'licence-'.$order->reference.'.pdf'),
        ]);
    }
}
