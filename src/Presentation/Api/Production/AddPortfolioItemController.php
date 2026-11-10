<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\AddPortfolioItem\AddPortfolioItem;
use App\Application\Production\AddPortfolioItem\AddPortfolioItemHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/production/portfolio', name: 'api_production_portfolio_add', methods: ['POST'], format: 'json')]
final class AddPortfolioItemController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] PortfolioItemPayload $payload, AddPortfolioItemHandler $addPortfolioItem): JsonResponse
    {
        return $this->json($addPortfolioItem(new AddPortfolioItem($payload->details())), JsonResponse::HTTP_CREATED);
    }
}
