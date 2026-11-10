<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\ResendWithdrawalAcknowledgement\ResendWithdrawalAcknowledgement;
use App\Application\Sales\ResendWithdrawalAcknowledgement\ResendWithdrawalAcknowledgementHandler;
use App\Application\Sales\SalesQueries;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/orders/{id}/withdrawal/resend', name: 'api_orders_withdrawal_resend', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class ResendWithdrawalAcknowledgementController extends AbstractController
{
    public function __invoke(Ulid $id, ResendWithdrawalAcknowledgementHandler $resend, SalesQueries $queries): JsonResponse
    {
        $resend(new ResendWithdrawalAcknowledgement($id));

        return $this->json(OrderJson::of($queries->order($id->toBase32()) ?? throw new NotFound('order', $id->toBase32())));
    }
}
