<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\ResendWithdrawalAcknowledgement\ResendWithdrawalAcknowledgement;
use App\Application\Sales\ResendWithdrawalAcknowledgement\ResendWithdrawalAcknowledgementHandler;
use App\Domain\Sales\WithdrawalRequest;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\InMemoryWithdrawals;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\RecordingTransaction;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ResendWithdrawalAcknowledgementTest extends TestCase
{
    public function testTheAcknowledgementIsSentAgainAndItsTimeUpdated(): void
    {
        $journal = new Journal();
        $orders = new InMemoryOrders();
        $withdrawals = new InMemoryWithdrawals();
        $mails = new RecordingSalesMails($journal);
        $order = SalesExamples::paid(paidAt: '2026-11-10 10:05:00 UTC');
        $order->withdraw(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));
        $orders->add($order);
        $request = WithdrawalRequest::receive($order, 'stripe@example.test', null, null, 'en', new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));
        $withdrawals->requests[] = $request;

        new ResendWithdrawalAcknowledgementHandler($orders, $withdrawals, $mails, new MockClock('2026-11-12 08:00:00 UTC'), new RecordingTransaction($journal))(new ResendWithdrawalAcknowledgement($order->id()));

        self::assertSame(['mail acknowledgement '.$order->reference(), 'commit'], $journal->entries);
        self::assertSame([$order->reference(), $request->id()->toBase32()], [$mails->acknowledgements[0][0]->reference, $mails->acknowledgements[0][1]->id]);
        self::assertEquals(new \DateTimeImmutable('2026-11-12 08:00:00 UTC'), $request->acknowledgedAt());
    }

    public function testAnOrderWithoutWithdrawalHasNothingToResend(): void
    {
        $orders = new InMemoryOrders();
        $order = SalesExamples::paid();
        $orders->add($order);

        $this->expectExceptionObject(new NotFound('withdrawal', $order->id()->toBase32()));

        new ResendWithdrawalAcknowledgementHandler($orders, new InMemoryWithdrawals(), new RecordingSalesMails(), new MockClock(), new RecordingTransaction(new Journal()))(new ResendWithdrawalAcknowledgement($order->id()));
    }
}
