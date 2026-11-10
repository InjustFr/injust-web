<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\RefundOrder\RefundOrder;
use App\Application\Sales\RefundOrder\RefundOrderHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/orders/{id}/refunds', name: 'api_orders_refund', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class RefundOrderController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] RefundPayload $payload, RefundOrderHandler $refund): JsonResponse
    {
        return $this->json(['refundId' => $refund(new RefundOrder($id, $payload->amountCents, trim($payload->reason)))], Response::HTTP_ACCEPTED);
    }
}
