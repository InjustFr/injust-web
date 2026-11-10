<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\RecordRefund\RecordRefund;
use App\Application\Sales\RecordRefund\RecordRefundHandler;
use App\Application\Sales\StripeRefund;
use App\Domain\Sales\Exception\InvalidRefundAmount;
use App\Domain\Sales\Exception\OrderNotFound;
use App\Domain\Sales\InvoiceSequence;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\FakePaymentGateway;
use App\Tests\Support\Sales\InMemoryCreditNotes;
use App\Tests\Support\Sales\InMemoryInvoices;
use App\Tests\Support\Sales\InMemoryInvoiceSequences;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\RecordingTransaction;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class RecordRefundTest extends TestCase
{
    private Journal $journal;

    private InMemoryOrders $orders;

    private InMemoryInvoices $invoices;

    private InMemoryInvoiceSequences $sequences;

    private InMemoryCreditNotes $creditNotes;

    private FakePaymentGateway $gateway;

    private RecordingSalesMails $mails;

    private Order $order;

    private string $paymentIntent;

    protected function setUp(): void
    {
        $this->journal = new Journal();
        $this->orders = new InMemoryOrders($this->journal);
        $this->invoices = new InMemoryInvoices();
        $this->sequences = new InMemoryInvoiceSequences($this->journal);
        $this->creditNotes = new InMemoryCreditNotes();
        $this->gateway = new FakePaymentGateway($this->journal);
        $this->mails = new RecordingSalesMails($this->journal);
        $this->order = SalesExamples::paid(SalesExamples::lesson(4500), paidAt: '2026-11-10 10:05:00 UTC');
        $this->paymentIntent = 'pi_'.$this->order->reference();
        $this->orders->add($this->order);
        $this->sequences->sequences[2026] = InvoiceSequence::start(2026);
        $number = $this->sequences->sequences[2026]->next();
        $this->order->invoiced($number);
        $this->invoices->add(DocumentExamples::invoiceOf($this->order, $number));
    }

    public function testAPartialRefundIsCreditedUnderLockWithTheNextNumberThenMailed(): void
    {
        $this->record([new StripeRefund('re_1', 1500, 'succeeded', 'Late cancellation')]);

        self::assertSame(['begin', 'lock order '.$this->paymentIntent, 'lock sequence 2026', 'end', 'begin', 'lock order '.$this->paymentIntent, 'mail credit note 2026-0002', 'commit', 'end'], $this->journal->entries);
        self::assertSame([OrderStatus::Paid, 1500], [$this->order->status(), $this->order->refundedCents()]);
        self::assertCount(1, $this->creditNotes->creditNotes);
        $note = $this->creditNotes->creditNotes[0];
        self::assertSame(['2026-0002', '2026-0001', 1500, 're_1', 'Late cancellation'], [$note->number(), $note->invoiceNumber(), $note->amount()->cents, $note->stripeRefundId(), $note->reason()]);
        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'), $note->issuedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'), $note->mailedAt());
        [$order, $mailed] = $this->mails->creditNotes[0];
        self::assertSame([$this->order->reference(), 1500, '2026-0002', $note->id()->toBase32()], [$order->reference, $order->refundedCents, $mailed->number, $mailed->id]);
    }

    public function testAPartialThenAFullRefundGiveTwoCreditNotesInTheSameSequenceAsInvoices(): void
    {
        $this->record([new StripeRefund('re_1', 1500, 'succeeded')]);
        $this->sequences->sequences[2026]->next();
        $this->record([new StripeRefund('re_2', 3000, 'succeeded'), new StripeRefund('re_1', 1500, 'succeeded')]);

        self::assertSame(['2026-0002', '2026-0004'], array_map(static fn ($note): string => $note->number(), $this->creditNotes->creditNotes));
        self::assertSame([OrderStatus::Refunded, 4500], [$this->order->status(), $this->order->refundedCents()]);
        self::assertSame(['2026-0002', '2026-0004'], array_map(static fn ($sent): string => $sent[1]->number, $this->mails->creditNotes));
    }

    public function testAReplayedEventCreditsNothingTwice(): void
    {
        $this->record([new StripeRefund('re_1', 4500, 'succeeded')]);
        $this->record([new StripeRefund('re_1', 4500, 'succeeded')]);

        self::assertCount(1, $this->creditNotes->creditNotes);
        self::assertCount(1, $this->mails->creditNotes);
        self::assertSame([4500, 2], [$this->order->refundedCents(), $this->sequences->sequences[2026]->last()]);
    }

    public function testOnlySucceededRefundsAreCredited(): void
    {
        $this->record([new StripeRefund('re_1', 1000, 'failed'), new StripeRefund('re_2', 1000, 'canceled'), new StripeRefund('re_3', 1000, 'pending'), new StripeRefund('re_4', 1000, 'requires_action'), new StripeRefund('re_5', 500, 'succeeded')]);

        self::assertSame(['re_5'], array_map(static fn ($note): string => $note->stripeRefundId(), $this->creditNotes->creditNotes));
        self::assertSame(500, $this->order->refundedCents());
    }

    public function testAPendingRefundIsCreditedOnceItSucceeds(): void
    {
        $this->record([new StripeRefund('re_1', 1000, 'pending')]);
        self::assertSame([[], 0], [$this->creditNotes->creditNotes, $this->order->refundedCents()]);

        $this->record([new StripeRefund('re_1', 1000, 'succeeded')]);

        self::assertSame(['re_1'], array_map(static fn ($note): string => $note->stripeRefundId(), $this->creditNotes->creditNotes));
        self::assertSame([1000, 2], [$this->order->refundedCents(), $this->sequences->sequences[2026]->last()]);
    }

    public function testAPendingRefundThatFailsIsNeverCreditedAndTheFullRefundStillFits(): void
    {
        $this->record([new StripeRefund('re_1', 4500, 'pending')]);
        $this->record([new StripeRefund('re_1', 4500, 'failed')]);
        $this->record([new StripeRefund('re_2', 4500, 'succeeded'), new StripeRefund('re_1', 4500, 'failed')]);

        self::assertSame(['re_2'], array_map(static fn ($note): string => $note->stripeRefundId(), $this->creditNotes->creditNotes));
        self::assertSame([OrderStatus::Refunded, 4500], [$this->order->status(), $this->order->refundedCents()]);
    }

    public function testTheRefundsAreAskedToStripeWhenTheEventDoesNotListThem(): void
    {
        $this->gateway->refunds[$this->paymentIntent] = [new StripeRefund('re_9', 4500, 'succeeded')];

        $this->record([]);

        self::assertSame(['begin', 'lock order '.$this->paymentIntent, 'refunds '.$this->paymentIntent, 'lock sequence 2026', 'end'], array_slice($this->journal->entries, 0, 5));
        self::assertSame(['re_9'], array_map(static fn ($note): string => $note->stripeRefundId(), $this->creditNotes->creditNotes));
    }

    public function testAnUnknownPaymentIsReportedWithoutAskingStripe(): void
    {
        try {
            $this->record([], 'pi_x');
            self::fail('An unknown payment was refunded.');
        } catch (OrderNotFound $missing) {
            self::assertEquals(new OrderNotFound('payment', 'pi_x'), $missing);
        }

        self::assertNotContains('refunds pi_x', $this->journal->entries);
    }

    public function testAPaymentWithoutInvoiceIsReported(): void
    {
        $this->invoices->invoices = [];

        $this->expectExceptionObject(new NotFound('invoice', $this->order->id()->toBase32()));

        $this->record([new StripeRefund('re_1', 1500, 'succeeded')]);
    }

    public function testARefundAboveThePriceTakesNoNumber(): void
    {
        try {
            $this->record([new StripeRefund('re_1', 4501, 'succeeded')]);
            self::fail('A refund above the price was credited.');
        } catch (InvalidRefundAmount $refused) {
            self::assertEquals(new InvalidRefundAmount(4500), $refused);
        }

        self::assertSame([[], 1, 0], [$this->creditNotes->creditNotes, $this->sequences->sequences[2026]->last(), $this->order->refundedCents()]);
        self::assertContains('rollback', $this->journal->entries);
    }

    public function testAFailedMailIsSentAgainOnTheNextDeliveryWithoutANewCreditNote(): void
    {
        $this->mails->failures = 1;

        try {
            $this->record([new StripeRefund('re_1', 1500, 'succeeded')]);
            self::fail('The mail failure was swallowed.');
        } catch (\RuntimeException $failure) {
            self::assertSame('SMTP is down.', $failure->getMessage());
        }
        self::assertNull($this->creditNotes->creditNotes[0]->mailedAt());

        $this->record([new StripeRefund('re_1', 1500, 'succeeded')]);

        self::assertCount(1, $this->creditNotes->creditNotes);
        self::assertSame(['2026-0002'], array_map(static fn ($sent): string => $sent[1]->number, $this->mails->creditNotes));
        self::assertNotNull($this->creditNotes->creditNotes[0]->mailedAt());
    }

    /** @param list<StripeRefund> $refunds */
    private function record(array $refunds, ?string $paymentIntent = null): void
    {
        $transaction = new RecordingTransaction($this->journal);

        new RecordRefundHandler($this->orders, $this->invoices, $this->sequences, $this->creditNotes, $this->gateway, $this->mails, new MockClock('2026-11-12 09:00:00 UTC'), $transaction, $transaction)(new RecordRefund($paymentIntent ?? $this->paymentIntent, $refunds));
    }
}
