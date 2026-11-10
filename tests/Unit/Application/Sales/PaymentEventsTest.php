<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\ExpireCheckout\ExpireCheckout;
use App\Application\Sales\ExpireCheckout\ExpireCheckoutHandler;
use App\Application\Sales\RefundPayment\RefundPayment;
use App\Application\Sales\RefundPayment\RefundPaymentHandler;
use App\Domain\Sales\Exception\OrderNotFound;
use App\Domain\Sales\OrderStatus;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingTransaction;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;

final class PaymentEventsTest extends TestCase
{
    public function testAnAbandonedCheckoutExpiresItsOrder(): void
    {
        $journal = new Journal();
        $orders = new InMemoryOrders();
        $order = SalesExamples::order();
        $order->attachSession('cs_1');
        $orders->add($order);

        new ExpireCheckoutHandler($orders, new RecordingTransaction($journal))(new ExpireCheckout('cs_1'));

        self::assertSame(OrderStatus::Expired, $order->status());
        self::assertSame(['commit'], $journal->entries);
    }

    public function testAnUnknownCheckoutIsReported(): void
    {
        $this->expectExceptionObject(new OrderNotFound('session', 'cs_x'));

        new ExpireCheckoutHandler(new InMemoryOrders(), new RecordingTransaction(new Journal()))(new ExpireCheckout('cs_x'));
    }

    public function testARefundMarksThePaidOrderRefunded(): void
    {
        $journal = new Journal();
        $orders = new InMemoryOrders();
        $order = SalesExamples::paid();
        $orders->add($order);

        new RefundPaymentHandler($orders, new RecordingTransaction($journal))(new RefundPayment('pi_'.$order->reference()));

        self::assertSame(OrderStatus::Refunded, $order->status());
        self::assertSame(['commit'], $journal->entries);
    }

    public function testARefundOfAnUnknownPaymentIsReported(): void
    {
        $this->expectExceptionObject(new OrderNotFound('payment', 'pi_x'));

        new RefundPaymentHandler(new InMemoryOrders(), new RecordingTransaction(new Journal()))(new RefundPayment('pi_x'));
    }
}
