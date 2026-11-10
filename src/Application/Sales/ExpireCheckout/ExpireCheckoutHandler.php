<?php

declare(strict_types=1);

namespace App\Application\Sales\ExpireCheckout;

use App\Application\Transaction;
use App\Domain\Sales\Exception\OrderNotFound;
use App\Domain\Sales\OrderRepository;

final readonly class ExpireCheckoutHandler
{
    public function __construct(
        private OrderRepository $orders,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ExpireCheckout $command): void
    {
        ($this->orders->bySession($command->sessionId) ?? throw new OrderNotFound('session', $command->sessionId))->expire();
        $this->transaction->commit();
    }
}
