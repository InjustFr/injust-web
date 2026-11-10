<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\RenameVideo\RenameVideo;
use App\Application\Music\RenameVideo\RenameVideoHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/videos/{id}', name: 'api_music_video_rename', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class RenameVideoController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] VideoTitlePayload $payload, RenameVideoHandler $renameVideo): JsonResponse
    {
        return $this->json($renameVideo(new RenameVideo($id, $payload->title)));
    }
}
