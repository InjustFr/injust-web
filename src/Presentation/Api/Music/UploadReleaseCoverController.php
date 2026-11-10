<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\UploadReleaseCover\UploadReleaseCover;
use App\Application\Music\UploadReleaseCover\UploadReleaseCoverHandler;
use App\Domain\Shared\Exception\UnsupportedImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/releases/{id}/cover', name: 'api_music_release_cover_upload', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class UploadReleaseCoverController extends AbstractController
{
    public function __invoke(Ulid $id, Request $request, UploadReleaseCoverHandler $uploadReleaseCover): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnsupportedImage();
        }

        return $this->json($uploadReleaseCover(new UploadReleaseCover($id, $file->getPathname())));
    }
}
