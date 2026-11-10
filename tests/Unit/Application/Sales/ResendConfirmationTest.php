<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\ResendConfirmation\ResendConfirmation;
use App\Application\Sales\ResendConfirmation\ResendConfirmationHandler;
use App\Domain\Sales\Exception\OrderNotPaid;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\Seller;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\Sales\InMemoryInvoices;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\RecordingTransaction;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ResendConfirmationTest extends TestCase
{
    public function testThePaidOrderConfirmationIsSentAgain(): void
    {
        $journal = new Journal();
        $orders = new InMemoryOrders();
        $invoices = new InMemoryInvoices();
        $order = SalesExamples::paid();
        $order->invoiced('2026-0004');
        $order->confirmationSent(new \DateTimeImmutable('2026-11-10 10:06:00'));
        $orders->add($order);
        $invoices->add(Invoice::issue('2026-0004', $order, Seller::of(LegalIdentity::blank()), VatMentions::defaults(), new \DateTimeImmutable('2026-11-10 10:06:00')));

        new ResendConfirmationHandler($orders, $invoices, new RecordingSalesMails($journal), new MockClock('2026-11-20 09:00:00'), new RecordingTransaction($journal))(new ResendConfirmation($order->id()));

        self::assertSame(['mail '.$order->reference().' 2026-0004', 'commit'], $journal->entries);
        self::assertEquals(new \DateTimeImmutable('2026-11-20 09:00:00'), $order->confirmationSentAt());
    }

    public function testAPaidOrderWithoutInvoiceIsReported(): void
    {
        $orders = new InMemoryOrders();
        $order = SalesExamples::paid();
        $orders->add($order);

        $this->expectExceptionObject(new NotFound('invoice', $order->id()->toBase32()));

        new ResendConfirmationHandler($orders, new InMemoryInvoices(), new RecordingSalesMails(), new MockClock(), new RecordingTransaction(new Journal()))(new ResendConfirmation($order->id()));
    }

    public function testAnUnpaidOrderHasNothingToResend(): void
    {
        $journal = new Journal();
        $orders = new InMemoryOrders();
        $order = SalesExamples::order();
        $orders->add($order);

        try {
            new ResendConfirmationHandler($orders, new InMemoryInvoices(), new RecordingSalesMails($journal), new MockClock(), new RecordingTransaction($journal))(new ResendConfirmation($order->id()));
            self::fail('An unpaid order was confirmed.');
        } catch (OrderNotPaid) {
        }

        self::assertSame([], $journal->entries);
    }
}
