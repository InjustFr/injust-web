<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\OrderBeats\OrderBeats;
use App\Application\Beats\OrderBeats\OrderBeatsHandler;
use App\Presentation\Api\Shared\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/beats/order', name: 'api_beats_order', methods: ['PUT'], format: 'json')]
final class OrderBeatsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderBeatsHandler $orderBeats): JsonResponse
    {
        return $this->json($orderBeats(new OrderBeats($payload->ids)));
    }
}
