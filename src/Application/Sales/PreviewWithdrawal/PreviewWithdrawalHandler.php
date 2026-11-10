<?php

declare(strict_types=1);

namespace App\Application\Sales\PreviewWithdrawal;

use App\Domain\Sales\Exception\NoMatchingOrder;
use App\Domain\Sales\OrderRepository;
use Psr\Clock\ClockInterface;

final readonly class PreviewWithdrawalHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PreviewWithdrawal $query): WithdrawalPreview
    {
        $order = $this->orders->byReference(strtoupper(trim($query->reference)));
        if (null === $order || !$order->belongsTo($query->email)) {
            throw new NoMatchingOrder();
        }
        $order->checkWithdrawal($this->clock->now());
        $paidAt = $order->paidAt();
        $lastDay = $order->withdrawalLastDay();
        \assert(null !== $paidAt && null !== $lastDay);

        return new WithdrawalPreview($order->reference(), $order->productType()->value, $order->productLabel(), $order->price()->cents, $paidAt, $lastDay);
    }
}
