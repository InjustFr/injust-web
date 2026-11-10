<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\ProductionQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/production', name: 'api_production_show', methods: ['GET'], format: 'json')]
final class ShowProductionController extends AbstractController
{
    public function __invoke(ProductionQueries $queries): JsonResponse
    {
        return $this->json(['page' => $queries->text(), 'services' => $queries->services(), 'portfolio' => $queries->portfolio()]);
    }
}
