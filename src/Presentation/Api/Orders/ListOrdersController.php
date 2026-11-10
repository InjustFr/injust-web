<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\SalesQueries;
use App\Domain\Sales\OrderStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/orders', name: 'api_orders_list', methods: ['GET'], format: 'json')]
final class ListOrdersController extends AbstractController
{
    public function __invoke(
        SalesQueries $queries,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?OrderStatus $status = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?int $year = null,
    ): JsonResponse {
        return $this->json(array_map(OrderJson::of(...), $queries->orders($status, $year)));
    }
}
