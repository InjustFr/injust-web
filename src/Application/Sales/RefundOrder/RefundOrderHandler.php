<?php

declare(strict_types=1);

namespace App\Application\Sales\RefundOrder;

use App\Application\Sales\PaymentGateway;
use App\Domain\Sales\Exception\RefundUnavailable;
use App\Domain\Sales\OrderRepository;

final readonly class RefundOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentGateway $gateway,
    ) {
    }

    public function __invoke(RefundOrder $command): string
    {
        $order = $this->orders->get($command->orderId);
        $order->checkRefund($command->amountCents);
        $paymentIntentId = $order->paymentIntentId();
        \assert(null !== $paymentIntentId);
        $reason = trim($command->reason);
        $key = $order->id()->toBase32().'-'.$order->refundedCents().'-'.$command->amountCents;
        try {
            return $this->gateway->refund($paymentIntentId, $command->amountCents, $key, '' === $reason ? null : $reason)->id;
        } catch (\RuntimeException) {
            throw new RefundUnavailable();
        }
    }
}
