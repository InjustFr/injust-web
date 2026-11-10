<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\DownloadLinks;
use App\Application\Sales\SalesQueries;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/admin/orders/{id}', name: 'api_orders_show', requirements: ['id' => Requirement::ULID], methods: ['GET'], format: 'json')]
final class ShowOrderController extends AbstractController
{
    public function __invoke(string $id, SalesQueries $queries, DownloadLinks $links): JsonResponse
    {
        $order = $queries->order($id) ?? throw new NotFound('order', $id);

        return $this->json(OrderJson::detail($order, $links->forOrder($order)));
    }
}
