<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\UploadBeatCover\UploadBeatCover;
use App\Application\Beats\UploadBeatCover\UploadBeatCoverHandler;
use App\Domain\Shared\Exception\UnsupportedImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/{id}/cover', name: 'api_beats_cover_upload', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class UploadBeatCoverController extends AbstractController
{
    public function __invoke(Ulid $id, Request $request, UploadBeatCoverHandler $uploadBeatCover): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnsupportedImage();
        }

        return $this->json($uploadBeatCover(new UploadBeatCover($id, $file->getPathname())));
    }
}
