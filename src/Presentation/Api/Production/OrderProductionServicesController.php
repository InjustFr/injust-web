<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\OrderProductionServices\OrderProductionServices;
use App\Application\Production\OrderProductionServices\OrderProductionServicesHandler;
use App\Presentation\Api\Shared\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/production/services/order', name: 'api_production_service_order', methods: ['PUT'], format: 'json')]
final class OrderProductionServicesController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderProductionServicesHandler $orderProductionServices): JsonResponse
    {
        return $this->json($orderProductionServices(new OrderProductionServices($payload->ids)));
    }
}
