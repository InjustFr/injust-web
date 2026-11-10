<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\UploadPortfolioCover\UploadPortfolioCover;
use App\Application\Production\UploadPortfolioCover\UploadPortfolioCoverHandler;
use App\Domain\Shared\Exception\UnsupportedImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/production/portfolio/{id}/cover', name: 'api_production_portfolio_cover_upload', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class UploadPortfolioCoverController extends AbstractController
{
    public function __invoke(Ulid $id, Request $request, UploadPortfolioCoverHandler $uploadPortfolioCover): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnsupportedImage();
        }

        return $this->json($uploadPortfolioCover(new UploadPortfolioCover($id, $file->getPathname())));
    }
}
