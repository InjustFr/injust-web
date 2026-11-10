<?php

declare(strict_types=1);

namespace App\Application\Sales\RefundPayment;

use App\Application\Transaction;
use App\Domain\Sales\Exception\OrderNotFound;
use App\Domain\Sales\OrderRepository;

final readonly class RefundPaymentHandler
{
    public function __construct(
        private OrderRepository $orders,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RefundPayment $command): void
    {
        ($this->orders->byPaymentIntent($command->paymentIntentId) ?? throw new OrderNotFound('payment', $command->paymentIntentId))->refund();
        $this->transaction->commit();
    }
}
