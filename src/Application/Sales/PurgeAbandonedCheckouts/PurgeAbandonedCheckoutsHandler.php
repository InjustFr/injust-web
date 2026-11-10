<?php

declare(strict_types=1);

namespace App\Application\Sales\PurgeAbandonedCheckouts;

use App\Application\Transaction;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use Psr\Clock\ClockInterface;

final readonly class PurgeAbandonedCheckoutsHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(PurgeAbandonedCheckouts $command): void
    {
        $this->orders->removeExpiredBefore($this->clock->now()->modify(Order::ABANDONED_RETENTION));
        $this->transaction->commit();
    }
}
