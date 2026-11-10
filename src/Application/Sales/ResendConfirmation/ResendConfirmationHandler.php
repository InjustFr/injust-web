<?php

declare(strict_types=1);

namespace App\Application\Sales\ResendConfirmation;

use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesMails;
use App\Application\Transaction;
use App\Domain\Sales\Exception\OrderNotPaid;
use App\Domain\Sales\InvoiceRepository;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use App\Domain\Shared\Exception\NotFound;
use Psr\Clock\ClockInterface;

final readonly class ResendConfirmationHandler
{
    public function __construct(
        private OrderRepository $orders,
        private InvoiceRepository $invoices,
        private SalesMails $mails,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ResendConfirmation $command): void
    {
        $order = $this->orders->get($command->orderId);
        if (OrderStatus::Paid !== $order->status()) {
            throw new OrderNotPaid($order->reference());
        }
        $invoice = $this->invoices->forOrder($order->id()) ?? throw new NotFound('invoice', $order->id()->toBase32());
        $this->mails->confirmation(OrderView::of($order), InvoiceView::of($invoice));
        $order->confirmationSent($this->clock->now());
        $this->transaction->commit();
    }
}
