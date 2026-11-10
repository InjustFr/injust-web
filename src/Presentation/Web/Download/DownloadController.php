<?php

declare(strict_types=1);

namespace App\Presentation\Web\Download;

use App\Application\Sales\Downloads;
use App\Domain\Beats\Deliverable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
#[Route('/download/{orderId}/{deliverable}', name: 'download', requirements: ['orderId' => Requirement::ULID, 'deliverable' => new EnumRequirement(Deliverable::class)], methods: ['GET'])]
final readonly class DownloadController
{
    public function __construct(
        private DownloadGate $gate,
        private Downloads $downloads,
    ) {
    }

    public function __invoke(Request $request, string $orderId, Deliverable $deliverable): Response
    {
        $file = $this->downloads->file($this->gate->open($request, $orderId), $deliverable) ?? throw new NotFoundHttpException();
        set_time_limit(0);

        $response = new BinaryFileResponse($file->path, headers: ['Content-Type' => $file->mimeType]);

        return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->filename);
    }
}
