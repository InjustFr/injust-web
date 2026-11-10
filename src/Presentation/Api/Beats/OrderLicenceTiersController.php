<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\OrderLicenceTiers\OrderLicenceTiers;
use App\Application\Beats\OrderLicenceTiers\OrderLicenceTiersHandler;
use App\Presentation\Api\Shared\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/beats/tiers/order', name: 'api_beats_tier_order', methods: ['PUT'], format: 'json')]
final class OrderLicenceTiersController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderLicenceTiersHandler $orderLicenceTiers): JsonResponse
    {
        return $this->json($orderLicenceTiers(new OrderLicenceTiers($payload->ids)));
    }
}
