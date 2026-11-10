<?php

declare(strict_types=1);

namespace App\Application\Sales\RecordRefund;

use App\Application\Atomic;
use App\Application\Sales\CreditNoteView;
use App\Application\Sales\OrderView;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\SalesMails;
use App\Application\Sales\StripeRefund;
use App\Application\Transaction;
use App\Domain\Sales\CreditNote;
use App\Domain\Sales\CreditNoteRepository;
use App\Domain\Sales\Exception\OrderNotFound;
use App\Domain\Sales\FiscalYear;
use App\Domain\Sales\InvoiceRepository;
use App\Domain\Sales\InvoiceSequenceRepository;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Shared\Exception\NotFound;
use Psr\Clock\ClockInterface;

final readonly class RecordRefundHandler
{
    public function __construct(
        private OrderRepository $orders,
        private InvoiceRepository $invoices,
        private InvoiceSequenceRepository $sequences,
        private CreditNoteRepository $creditNotes,
        private PaymentGateway $gateway,
        private SalesMails $mails,
        private ClockInterface $clock,
        private Atomic $atomic,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RecordRefund $command): void
    {
        $this->atomic->atomically(fn () => $this->record($command));
        $this->atomic->atomically(fn () => $this->mail($command->paymentIntentId));
    }

    private function record(RecordRefund $command): void
    {
        $order = $this->lock($command->paymentIntentId);
        foreach ($this->refundsOf($command) as $refund) {
            if ($refund->succeeded() && null === $this->creditNotes->byRefund($refund->id)) {
                $this->credit($order, $refund);
            }
        }
    }

    /** @return list<StripeRefund> */
    private function refundsOf(RecordRefund $command): array
    {
        return [] === $command->refunds ? $this->gateway->refunds($command->paymentIntentId) : $command->refunds;
    }

    private function credit(Order $order, StripeRefund $refund): void
    {
        $order->refund($refund->amountCents);
        $invoice = $this->invoices->forOrder($order->id()) ?? throw new NotFound('invoice', $order->id()->toBase32());
        $now = $this->clock->now();
        $number = $this->sequences->lock(FiscalYear::of($now))->next();
        $this->creditNotes->add(CreditNote::issue($number, $invoice, $refund->amountCents, $refund->id, $refund->reason, $now));
    }

    private function mail(string $paymentIntentId): void
    {
        $order = $this->lock($paymentIntentId);
        foreach ($this->creditNotes->forOrder($order->id()) as $note) {
            if (null === $note->mailedAt()) {
                $this->mails->creditNote(OrderView::of($order), CreditNoteView::of($note));
                $note->mailed($this->clock->now());
                $this->transaction->commit();
            }
        }
    }

    private function lock(string $paymentIntentId): Order
    {
        return $this->orders->lockByPaymentIntent($paymentIntentId) ?? throw new OrderNotFound('payment', $paymentIntentId);
    }
}
