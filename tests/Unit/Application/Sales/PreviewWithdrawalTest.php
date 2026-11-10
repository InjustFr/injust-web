<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\PreviewWithdrawal\PreviewWithdrawal;
use App\Application\Sales\PreviewWithdrawal\PreviewWithdrawalHandler;
use App\Application\Sales\PreviewWithdrawal\WithdrawalPreview;
use App\Domain\Sales\Exception\NoMatchingOrder;
use App\Domain\Sales\Exception\WithdrawalNotAllowed;
use App\Domain\Sales\Exception\WithdrawalPeriodOver;
use App\Domain\Sales\OrderStatus;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PreviewWithdrawalTest extends TestCase
{
    private Journal $journal;

    private InMemoryOrders $orders;

    protected function setUp(): void
    {
        $this->journal = new Journal();
        $this->orders = new InMemoryOrders($this->journal);
    }

    public function testThePreviewSummarisesTheOrderWithoutChangingIt(): void
    {
        $order = SalesExamples::paid(SalesExamples::production(12000), paidAt: '2026-03-28 23:30:00 Europe/Paris');
        $this->orders->add($order);

        $preview = $this->preview(new PreviewWithdrawal(' '.strtolower($order->reference()), 'STRIPE@example.test '), '2026-04-01 10:00:00 UTC');

        self::assertEquals(new WithdrawalPreview($order->reference(), 'production', 'Mixage', 12000, new \DateTimeImmutable('2026-03-28 23:30:00 Europe/Paris'), new \DateTimeImmutable('2026-04-13 00:00:00 UTC')), $preview);
        self::assertSame(OrderStatus::Paid, $order->status());
        self::assertSame([], $this->journal->entries);
    }

    public function testAMismatchIsRefusedGenerically(): void
    {
        $order = SalesExamples::paid();
        $this->orders->add($order);

        foreach ([[$order->reference(), 'other@example.test'], ['INJ-ZZZZZZ', 'stripe@example.test']] as [$reference, $email]) {
            try {
                $this->preview(new PreviewWithdrawal($reference, $email), '2026-11-10 10:06:00 UTC');
                self::fail('A mismatch was previewed.');
            } catch (NoMatchingOrder $refused) {
                self::assertEquals(new NoMatchingOrder(), $refused);
            }
        }
    }

    public function testThePreviewRefusesALateWithdrawal(): void
    {
        $order = SalesExamples::paid(paidAt: '2026-03-28 23:30:00 Europe/Paris');
        $this->orders->add($order);

        $this->expectException(WithdrawalPeriodOver::class);

        $this->preview(new PreviewWithdrawal($order->reference(), 'stripe@example.test'), '2026-04-14 00:00:00 Europe/Paris');
    }

    public function testThePreviewRefusesABeat(): void
    {
        $order = SalesExamples::paid(SalesExamples::beat());
        $this->orders->add($order);

        $this->expectExceptionObject(new WithdrawalNotAllowed($order->reference()));

        $this->preview(new PreviewWithdrawal($order->reference(), 'stripe@example.test'), '2026-11-10 10:06:00 UTC');
    }

    private function preview(PreviewWithdrawal $query, string $now): WithdrawalPreview
    {
        return new PreviewWithdrawalHandler($this->orders, new MockClock($now))($query);
    }
}
