<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\DownloadLinks;
use App\Application\Sales\ResendConfirmation\ResendConfirmation;
use App\Application\Sales\ResendConfirmation\ResendConfirmationHandler;
use App\Application\Sales\SalesQueries;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/orders/{id}/resend', name: 'api_orders_resend', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class ResendOrderConfirmationController extends AbstractController
{
    public function __invoke(Ulid $id, ResendConfirmationHandler $resend, SalesQueries $queries, DownloadLinks $links): JsonResponse
    {
        $resend(new ResendConfirmation($id));
        $order = $queries->order($id->toBase32()) ?? throw new NotFound('order', $id->toBase32());

        return $this->json(OrderJson::detail($order, $links->forOrder($order)));
    }
}
