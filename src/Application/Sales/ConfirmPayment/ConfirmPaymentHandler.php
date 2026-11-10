<?php

declare(strict_types=1);

namespace App\Application\Sales\ConfirmPayment;

use App\Application\Atomic;
use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\SalesMails;
use App\Application\Transaction;
use App\Domain\Sales\Exception\OrderNotFound;
use App\Domain\Sales\FiscalYear;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceRepository;
use App\Domain\Sales\InvoiceSequenceRepository;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\Seller;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Shared\Exception\NotFound;
use Psr\Clock\ClockInterface;

final readonly class ConfirmPaymentHandler
{
    public function __construct(
        private OrderRepository $orders,
        private InvoiceRepository $invoices,
        private InvoiceSequenceRepository $sequences,
        private SettingsRepository $settings,
        private PaymentGateway $gateway,
        private SalesMails $mails,
        private ClockInterface $clock,
        private Atomic $atomic,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ConfirmPayment $command): void
    {
        $this->atomic->atomically(fn () => $this->record($command->sessionId));
        $this->atomic->atomically(fn () => $this->confirm($command->sessionId));
    }

    private function record(string $sessionId): void
    {
        $order = $this->lock($sessionId);
        if ($order->pay($this->gateway->paymentDetails($sessionId))) {
            $now = $this->clock->now();
            $settings = $this->settings->current();
            $number = $this->sequences->lock(FiscalYear::of($now))->next();
            $order->invoiced($number);
            $this->invoices->add(Invoice::issue($number, $order, Seller::of($settings->legalIdentity()), $settings->vatMentions(), $now));
        }
    }

    private function confirm(string $sessionId): void
    {
        $order = $this->lock($sessionId);
        if (!$order->needsConfirmation()) {
            return;
        }

        $invoice = $this->invoices->forOrder($order->id()) ?? throw new NotFound('invoice', $order->id()->toBase32());
        $this->mails->confirmation(OrderView::of($order), InvoiceView::of($invoice));
        $order->confirmationSent($this->clock->now());
        $this->transaction->commit();
    }

    private function lock(string $sessionId): Order
    {
        return $this->orders->lockBySession($sessionId) ?? throw new OrderNotFound('session', $sessionId);
    }
}
