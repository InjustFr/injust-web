<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\RefundOrder\RefundOrder;
use App\Application\Sales\RefundOrder\RefundOrderHandler;
use App\Domain\Sales\Exception\InvalidRefundAmount;
use App\Domain\Sales\Exception\RefundBeforePayment;
use App\Domain\Sales\Exception\RefundUnavailable;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Tests\Support\Sales\FakePaymentGateway;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;

final class RefundOrderTest extends TestCase
{
    private InMemoryOrders $orders;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrders();
        $this->gateway = new FakePaymentGateway();
    }

    public function testTheRefundIsAskedToStripeAndTheCreditNoteIsLeftToTheWebhook(): void
    {
        $order = $this->stored(SalesExamples::paid(SalesExamples::lesson(4500)));

        $refundId = $this->refund(new RefundOrder($order->id(), 1500, 'Late cancellation'));

        self::assertSame('re_1', $refundId);
        $id = $order->id()->toBase32();
        self::assertSame([['pi_'.$order->reference(), 1500, $id.'-0-1500', 'Late cancellation']], $this->gateway->refundRequests);
        self::assertSame([OrderStatus::Paid, 0], [$order->status(), $order->refundedCents()]);
    }

    public function testTheIdempotencyKeyFollowsWhatIsAlreadyRefunded(): void
    {
        $order = $this->stored(SalesExamples::paid(SalesExamples::lesson(4500), paidAt: '2026-11-10 10:05:00 UTC'));
        $order->withdraw(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));
        $order->refund(1500);

        $this->refund(new RefundOrder($order->id(), 3000, '  '));

        self::assertSame([['pi_'.$order->reference(), 3000, $order->id()->toBase32().'-1500-3000', null]], $this->gateway->refundRequests);
    }

    public function testMoreThanWhatRemainsIsRefused(): void
    {
        $order = $this->stored(SalesExamples::paid(SalesExamples::lesson(4500)));
        $order->refund(1500);

        $this->refused(new RefundOrder($order->id(), 3001, ''), new InvalidRefundAmount(3000));
    }

    public function testNothingIsRefusedAsAnAmount(): void
    {
        $order = $this->stored(SalesExamples::paid(SalesExamples::lesson(4500)));

        $this->refused(new RefundOrder($order->id(), 0, ''), new InvalidRefundAmount(4500));
    }

    public function testAnUnpaidOrderIsNotRefunded(): void
    {
        $order = $this->stored(SalesExamples::order());

        $this->refused(new RefundOrder($order->id(), 100, ''), new RefundBeforePayment($order->reference()));
    }

    public function testAStripeFailureIsReported(): void
    {
        $order = $this->stored(SalesExamples::paid());
        $this->gateway->down = true;

        $this->expectExceptionObject(new RefundUnavailable());

        $this->refund(new RefundOrder($order->id(), 100, ''));
    }

    private function refused(RefundOrder $command, \Throwable $expected): void
    {
        try {
            $this->refund($command);
            self::fail('The refund was asked.');
        } catch (\Throwable $refusal) {
            self::assertEquals($expected, $refusal);
        }

        self::assertSame([], $this->gateway->refundRequests);
    }

    private function stored(Order $order): Order
    {
        $this->orders->add($order);

        return $order;
    }

    private function refund(RefundOrder $command): string
    {
        return new RefundOrderHandler($this->orders, $this->gateway)($command);
    }
}
