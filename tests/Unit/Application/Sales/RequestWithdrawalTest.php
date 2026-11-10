<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\RequestWithdrawal\RequestWithdrawal;
use App\Application\Sales\RequestWithdrawal\RequestWithdrawalHandler;
use App\Application\Sales\WithdrawalRequestView;
use App\Domain\Sales\Exception\NoMatchingOrder;
use App\Domain\Sales\Exception\WithdrawalNotAllowed;
use App\Domain\Sales\Exception\WithdrawalPeriodOver;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\InMemoryWithdrawals;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\RecordingTransaction;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class RequestWithdrawalTest extends TestCase
{
    private Journal $journal;

    private InMemoryOrders $orders;

    private InMemoryWithdrawals $withdrawals;

    private RecordingSalesMails $mails;

    private BufferingLogger $logger;

    private MockClock $clock;

    private Order $order;

    protected function setUp(): void
    {
        $this->journal = new Journal();
        $this->orders = new InMemoryOrders($this->journal);
        $this->withdrawals = new InMemoryWithdrawals($this->journal);
        $this->mails = new RecordingSalesMails($this->journal);
        $this->logger = new BufferingLogger();
        $this->clock = new MockClock('2026-11-12 09:00:00 UTC');
        $this->order = SalesExamples::paid(SalesExamples::lesson(4500), paidAt: '2026-11-10 10:05:00 UTC');
        $this->orders->add($this->order);
        $this->orders->add(SalesExamples::paid(SalesExamples::production(), paidAt: '2026-11-10 10:05:00 UTC'));
    }

    public function testTheWithdrawalIsStoredUnderLockThenAcknowledgedByMail(): void
    {
        $reference = $this->order->reference();

        $view = $this->request(new RequestWithdrawal($reference, 'stripe@example.test', 'Test Buyer', 'Changed my mind', 'en'));

        self::assertSame(['begin', 'lock order '.$reference, 'store withdrawal '.$reference, 'commit', 'end', 'mail acknowledgement '.$reference, 'commit', 'mail notice '.$reference], $this->journal->entries);
        self::assertSame(OrderStatus::Withdrawn, $this->order->status());
        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'), $this->order->withdrawnAt());
        $request = $this->withdrawals->requests[0];
        self::assertSame([$reference, 'stripe@example.test', 'Test Buyer', 'Changed my mind', 'en'], [$request->orderReference(), $request->email(), $request->name(), $request->reason(), $request->locale()]);
        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'), $request->receivedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'), $request->acknowledgedAt());
        self::assertEquals(WithdrawalRequestView::of($request), $view);
        [$order, $acknowledged] = $this->mails->acknowledgements[0];
        self::assertSame([$reference, 'withdrawn', $request->id()->toBase32()], [$order->reference, $order->status, $acknowledged->id]);
        self::assertSame([$reference, $request->id()->toBase32()], [$this->mails->notices[0][0]->reference, $this->mails->notices[0][1]->id]);
        self::assertSame([], $this->logger->cleanLogs());
    }

    public function testTheReferenceAndEmailAreMatchedWhateverTheirCaseAndSpaces(): void
    {
        $this->request(new RequestWithdrawal(' '.strtolower($this->order->reference()).' ', ' STRIPE@Example.TEST ', null, null, 'fr'));

        self::assertSame(OrderStatus::Withdrawn, $this->order->status());
        self::assertNull($this->withdrawals->requests[0]->name());
    }

    /** @return iterable<string, array{string, string}> */
    public static function mismatches(): iterable
    {
        yield 'another email' => ['', 'other@example.test'];
        yield 'blank email' => ['', ''];
        yield 'unknown reference' => ['INJ-ZZZZZZ', 'stripe@example.test'];
    }

    #[DataProvider('mismatches')]
    public function testAMismatchIsRefusedWithoutSayingWhichPartIsWrong(string $reference, string $email): void
    {
        try {
            $this->request(new RequestWithdrawal('' === $reference ? $this->order->reference() : $reference, $email, null, null, 'fr'));
            self::fail('A mismatching withdrawal was accepted.');
        } catch (NoMatchingOrder $refused) {
            self::assertEquals(new NoMatchingOrder(), $refused);
        }

        $this->assertNothingHappened();
    }

    public function testALateWithdrawalIsRefusedAndNothingIsStored(): void
    {
        $this->clock->modify('2026-11-25 00:00:00 Europe/Paris');

        try {
            $this->request(new RequestWithdrawal($this->order->reference(), 'stripe@example.test', null, null, 'fr'));
            self::fail('A late withdrawal was accepted.');
        } catch (WithdrawalPeriodOver $over) {
            self::assertEquals(['endDate' => new \DateTimeImmutable('2026-11-24 00:00:00 UTC')], $over->parameters());
        }

        $this->assertNothingHappened();
    }

    public function testABeatIsNotWithdrawn(): void
    {
        $beat = SalesExamples::paid(SalesExamples::beat(), paidAt: '2026-11-10 10:05:00 UTC');
        $this->orders->add($beat);

        $this->expectExceptionObject(new WithdrawalNotAllowed($beat->reference()));

        $this->request(new RequestWithdrawal($beat->reference(), 'stripe@example.test', null, null, 'fr'));
    }

    public function testAFailedAcknowledgementKeepsTheWithdrawalAndIsLogged(): void
    {
        $this->mails->failures = 1;
        $reference = $this->order->reference();

        $view = $this->request(new RequestWithdrawal($reference, 'stripe@example.test', null, null, 'fr'));

        self::assertSame(['begin', 'lock order '.$reference, 'store withdrawal '.$reference, 'commit', 'end', 'mail failed', 'mail notice '.$reference], $this->journal->entries);
        self::assertSame(OrderStatus::Withdrawn, $this->order->status());
        self::assertNull($view->acknowledgedAt);
        self::assertNull($this->withdrawals->requests[0]->acknowledgedAt());
        self::assertSame([['error', 'The withdrawal {mail} of order {reference} was not sent: {error}', ['mail' => 'acknowledgement', 'reference' => $reference, 'error' => \RuntimeException::class]]], $this->logger->cleanLogs());
    }

    public function testAFailedNoticeKeepsTheAcknowledgementAndIsLogged(): void
    {
        $this->mails->broken = 'notice';
        $reference = $this->order->reference();

        $view = $this->request(new RequestWithdrawal($reference, 'stripe@example.test', null, null, 'fr'));

        self::assertSame(['begin', 'lock order '.$reference, 'store withdrawal '.$reference, 'commit', 'end', 'mail acknowledgement '.$reference, 'commit', 'mail failed'], $this->journal->entries);
        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'), $view->acknowledgedAt);
        self::assertSame([['error', 'The withdrawal {mail} of order {reference} was not sent: {error}', ['mail' => 'notice', 'reference' => $reference, 'error' => \RuntimeException::class]]], $this->logger->cleanLogs());
    }

    private function assertNothingHappened(): void
    {
        self::assertSame(OrderStatus::Paid, $this->order->status());
        self::assertSame([], $this->withdrawals->requests);
        self::assertSame([[], []], [$this->mails->acknowledgements, $this->mails->notices]);
    }

    private function request(RequestWithdrawal $command): WithdrawalRequestView
    {
        $transaction = new RecordingTransaction($this->journal);

        return new RequestWithdrawalHandler($this->orders, $this->withdrawals, $this->mails, $this->clock, $transaction, $transaction, $this->logger)($command);
    }
}
