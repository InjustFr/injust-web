<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\AddVideo\AddVideo;
use App\Application\Music\AddVideo\AddVideoHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/videos', name: 'api_music_video_add', methods: ['POST'], format: 'json')]
final class AddVideoController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] VideoPayload $payload, AddVideoHandler $addVideo): JsonResponse
    {
        return $this->json($addVideo(new AddVideo($payload->youtubeId(), $payload->title)), JsonResponse::HTTP_CREATED);
    }
}
