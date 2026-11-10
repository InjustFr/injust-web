<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\UploadBeatPreview\UploadBeatPreview;
use App\Application\Beats\UploadBeatPreview\UploadBeatPreviewHandler;
use App\Domain\Beats\Exception\BeatUploadFailed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/{id}/preview', name: 'api_beats_preview_upload', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class UploadBeatPreviewController extends AbstractController
{
    public function __invoke(Ulid $id, Request $request, UploadBeatPreviewHandler $uploadBeatPreview): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new BeatUploadFailed();
        }

        return $this->json($uploadBeatPreview(new UploadBeatPreview($id, $file->getPathname(), $file->getClientOriginalName())));
    }
}
