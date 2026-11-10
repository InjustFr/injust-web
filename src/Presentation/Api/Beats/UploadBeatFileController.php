<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\UploadBeatFile\UploadBeatFile;
use App\Application\Beats\UploadBeatFile\UploadBeatFileHandler;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\Exception\BeatUploadFailed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/{id}/files/{deliverable}', name: 'api_beats_file_upload', requirements: ['id' => Requirement::ULID, 'deliverable' => new EnumRequirement(Deliverable::class)], methods: ['POST'], format: 'json')]
final class UploadBeatFileController extends AbstractController
{
    public function __invoke(Ulid $id, Deliverable $deliverable, Request $request, UploadBeatFileHandler $uploadBeatFile): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new BeatUploadFailed();
        }

        return $this->json($uploadBeatFile(new UploadBeatFile($id, $deliverable, $file->getPathname(), $file->getClientOriginalName())));
    }
}
