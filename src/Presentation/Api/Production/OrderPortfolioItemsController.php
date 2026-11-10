<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\OrderPortfolioItems\OrderPortfolioItems;
use App\Application\Production\OrderPortfolioItems\OrderPortfolioItemsHandler;
use App\Presentation\Api\Shared\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/production/portfolio/order', name: 'api_production_portfolio_order', methods: ['PUT'], format: 'json')]
final class OrderPortfolioItemsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderPortfolioItemsHandler $orderPortfolioItems): JsonResponse
    {
        return $this->json($orderPortfolioItems(new OrderPortfolioItems($payload->ids)));
    }
}
