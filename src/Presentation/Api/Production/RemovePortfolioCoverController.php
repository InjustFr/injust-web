<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\RemovePortfolioCover\RemovePortfolioCover;
use App\Application\Production\RemovePortfolioCover\RemovePortfolioCoverHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/production/portfolio/{id}/cover', name: 'api_production_portfolio_cover_remove', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class RemovePortfolioCoverController extends AbstractController
{
    public function __invoke(Ulid $id, RemovePortfolioCoverHandler $removePortfolioCover): JsonResponse
    {
        return $this->json($removePortfolioCover(new RemovePortfolioCover($id)));
    }
}
