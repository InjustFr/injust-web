<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\EditProductionService\EditProductionService;
use App\Application\Production\EditProductionService\EditProductionServiceHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/production/services/{id}', name: 'api_production_service_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditProductionServiceController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] ProductionServicePayload $payload, EditProductionServiceHandler $editProductionService): JsonResponse
    {
        return $this->json($editProductionService(new EditProductionService($id, $payload->details())));
    }
}
