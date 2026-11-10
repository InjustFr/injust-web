<?php

declare(strict_types=1);

namespace App\Application\Sales\ResendWithdrawalAcknowledgement;

use App\Application\Sales\OrderView;
use App\Application\Sales\SalesMails;
use App\Application\Sales\WithdrawalRequestView;
use App\Application\Transaction;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\WithdrawalRequestRepository;
use App\Domain\Shared\Exception\NotFound;
use Psr\Clock\ClockInterface;

final readonly class ResendWithdrawalAcknowledgementHandler
{
    public function __construct(
        private OrderRepository $orders,
        private WithdrawalRequestRepository $withdrawals,
        private SalesMails $mails,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ResendWithdrawalAcknowledgement $command): void
    {
        $order = $this->orders->get($command->orderId);
        $request = $this->withdrawals->forOrder($order->id()) ?? throw new NotFound('withdrawal', $order->id()->toBase32());
        $this->mails->withdrawalAcknowledgement(OrderView::of($order, $request), WithdrawalRequestView::of($request));
        $request->acknowledged($this->clock->now());
        $this->transaction->commit();
    }
}
