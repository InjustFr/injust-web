<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\OrderReleases\OrderReleases;
use App\Application\Music\OrderReleases\OrderReleasesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/releases/order', name: 'api_music_release_order', methods: ['PUT'], format: 'json')]
final class OrderReleasesController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderReleasesHandler $orderReleases): JsonResponse
    {
        return $this->json($orderReleases(new OrderReleases($payload->ids)));
    }
}
