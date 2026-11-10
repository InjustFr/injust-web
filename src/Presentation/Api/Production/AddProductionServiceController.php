<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\AddProductionService\AddProductionService;
use App\Application\Production\AddProductionService\AddProductionServiceHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/production/services', name: 'api_production_service_add', methods: ['POST'], format: 'json')]
final class AddProductionServiceController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] ProductionServicePayload $payload, AddProductionServiceHandler $addProductionService): JsonResponse
    {
        return $this->json($addProductionService(new AddProductionService($payload->details())), JsonResponse::HTTP_CREATED);
    }
}
