<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\OrderVideos\OrderVideos;
use App\Application\Music\OrderVideos\OrderVideosHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/videos/order', name: 'api_music_video_order', methods: ['PUT'], format: 'json')]
final class OrderVideosController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderVideosHandler $orderVideos): JsonResponse
    {
        return $this->json($orderVideos(new OrderVideos($payload->ids)));
    }
}
