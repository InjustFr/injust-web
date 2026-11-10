<?php

declare(strict_types=1);

namespace App\Application\Sales\ConfirmPayment;

use App\Application\Atomic;
use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\SalesMails;
use App\Application\Transaction;
use App\Domain\Beats\BeatRepository;
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
use Symfony\Component\Uid\Ulid;

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
        private BeatRepository $beats,
    ) {
    }

    public function __invoke(ConfirmPayment $command): void
    {
        $this->atomic->atomically(fn () => $this->record($command->sessionId));
        if ($this->atomic->atomically(fn (): bool => $this->confirm($command->sessionId))) {
            $this->atomic->atomically(fn () => $this->noticeConflict($command->sessionId));
        }
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
            $this->sellBeat($order, $now);
        }
    }

    private function sellBeat(Order $order, \DateTimeImmutable $now): void
    {
        $beatId = $order->licence()?->beat->beatId;
        $beat = null === $beatId ? null : $this->beats->lock(Ulid::fromString($beatId));
        if (null !== $beat) {
            $order->sell($beat, $now);
        }
    }

    private function confirm(string $sessionId): bool
    {
        $order = $this->lock($sessionId);
        if ($order->needsConfirmation()) {
            $invoice = $this->invoices->forOrder($order->id()) ?? throw new NotFound('invoice', $order->id()->toBase32());
            $this->mails->confirmation(OrderView::of($order), InvoiceView::of($invoice));
            $order->confirmationSent($this->clock->now());
            $this->transaction->commit();
        }

        return $order->needsConflictNotice();
    }

    private function noticeConflict(string $sessionId): void
    {
        $order = $this->lock($sessionId);
        if ($order->needsConflictNotice()) {
            $this->mails->exclusiveConflict(OrderView::of($order));
            $order->conflictNoticeSent($this->clock->now());
            $this->transaction->commit();
        }
    }

    private function lock(string $sessionId): Order
    {
        return $this->orders->lockBySession($sessionId) ?? throw new OrderNotFound('session', $sessionId);
    }
}
