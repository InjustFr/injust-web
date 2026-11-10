<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\DeleteProductionService\DeleteProductionService;
use App\Application\Production\DeleteProductionService\DeleteProductionServiceHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/production/services/{id}', name: 'api_production_service_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteProductionServiceController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteProductionServiceHandler $deleteProductionService): Response
    {
        $deleteProductionService(new DeleteProductionService($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
