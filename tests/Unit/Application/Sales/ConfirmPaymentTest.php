<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Sales\Exception\OrderNotFound;
use App\Domain\Sales\Exception\PaymentMismatch;
use App\Domain\Sales\InvoiceSequence;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\VatCase;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Shared\LocalizedText;
use App\Tests\Support\Sales\FakePaymentGateway;
use App\Tests\Support\Sales\InMemoryInvoices;
use App\Tests\Support\Sales\InMemoryInvoiceSequences;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\RecordingTransaction;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ConfirmPaymentTest extends TestCase
{
    private Journal $journal;

    private InMemoryOrders $orders;

    private InMemoryInvoices $invoices;

    private InMemoryInvoiceSequences $sequences;

    private FakePaymentGateway $gateway;

    private RecordingSalesMails $mails;

    private Order $order;

    protected function setUp(): void
    {
        $this->journal = new Journal();
        $this->orders = new InMemoryOrders($this->journal);
        $this->invoices = new InMemoryInvoices();
        $this->sequences = new InMemoryInvoiceSequences($this->journal);
        $this->gateway = new FakePaymentGateway($this->journal);
        $this->mails = new RecordingSalesMails($this->journal);
        $this->order = SalesExamples::order(SalesExamples::lesson(4500));
        $this->order->attachSession('cs_1');
        $this->orders->add($this->order);
        $this->gateway->payments['cs_1'] = SalesExamples::payment($this->order, SalesExamples::customer('DE', 'DE123456789', 'Riot GmbH'));
    }

    public function testThePaymentIsRecordedAndInvoicedUnderLockThenConfirmedByMail(): void
    {
        $this->confirm('cs_1');

        $reference = $this->order->reference();
        self::assertSame(['begin', 'lock order cs_1', 'details cs_1', 'lock sequence 2026', 'end', 'begin', 'lock order cs_1', 'mail '.$reference.' 2026-0001', 'commit', 'end'], $this->journal->entries);
        self::assertSame([OrderStatus::Paid, '2026-0001'], [$this->order->status(), $this->order->invoiceNumber()]);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00'), $this->order->confirmationSentAt());
        self::assertCount(1, $this->invoices->invoices);
        $invoice = $this->invoices->invoices[0];
        self::assertSame(['2026-0001', 'Louis Injust', VatCase::EuBusiness, 'Reverse charge', 4500], [$invoice->number(), $invoice->seller()->name, $invoice->vatCase(), $invoice->vatMention(), $invoice->total()->cents]);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00'), $invoice->issuedAt());
        [$orderView, $invoiceView] = $this->mails->sent[0];
        self::assertSame([$reference, 'paid', '2026-0001', '2026-0001'], [$orderView->reference, $orderView->status, $orderView->invoiceNumber, $invoiceView->number]);
    }

    public function testTheNumberContinuesTheSequenceOfTheYearOfIssueInParis(): void
    {
        $this->sequences->sequences[2027] = InvoiceSequence::start(2027);
        $this->sequences->sequences[2027]->next();

        $this->confirm('cs_1', '2026-12-31 23:30:00 UTC');

        self::assertSame('2027-0002', $this->order->invoiceNumber());
    }

    public function testAReplayedEventChangesNothing(): void
    {
        $this->confirm('cs_1');
        $this->journal->entries = [];

        $this->confirm('cs_1', '2026-11-12 10:00:00');

        self::assertSame(['begin', 'lock order cs_1', 'details cs_1', 'end', 'begin', 'lock order cs_1', 'end'], $this->journal->entries);
        self::assertCount(1, $this->invoices->invoices);
        self::assertCount(1, $this->mails->sent);
        self::assertSame(1, $this->sequences->sequences[2026]->last());
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00'), $this->order->confirmationSentAt());
    }

    public function testAFailedMailKeepsThePaymentAndTheInvoiceAndOnlyTheMailIsRetried(): void
    {
        $this->mails->failures = 1;

        try {
            $this->confirm('cs_1');
            self::fail('The mail failure was swallowed.');
        } catch (\RuntimeException $failure) {
            self::assertSame('SMTP is down.', $failure->getMessage());
        }

        self::assertSame([OrderStatus::Paid, '2026-0001', null], [$this->order->status(), $this->order->invoiceNumber(), $this->order->confirmationSentAt()]);
        self::assertCount(1, $this->invoices->invoices);
        self::assertSame(['begin', 'lock order cs_1', 'details cs_1', 'lock sequence 2026', 'end', 'begin', 'lock order cs_1', 'mail failed', 'rollback'], $this->journal->entries);
        $this->journal->entries = [];

        $this->confirm('cs_1', '2026-11-10 10:10:00');

        self::assertSame(['begin', 'lock order cs_1', 'details cs_1', 'end', 'begin', 'lock order cs_1', 'mail '.$this->order->reference().' 2026-0001', 'commit', 'end'], $this->journal->entries);
        self::assertCount(1, $this->invoices->invoices);
        self::assertSame(1, $this->sequences->sequences[2026]->last());
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:10:00'), $this->order->confirmationSentAt());
    }

    public function testAPaidOrderWithoutInvoiceIsReportedInsteadOfMailed(): void
    {
        $this->order->pay(SalesExamples::payment($this->order));

        $this->expectExceptionObject(new NotFound('invoice', $this->order->id()->toBase32()));

        $this->confirm('cs_1');
    }

    public function testAnUnknownSessionIsReported(): void
    {
        try {
            $this->confirm('cs_unknown');
            self::fail('An unknown session was confirmed.');
        } catch (OrderNotFound $notFound) {
            self::assertSame(['by' => 'session', 'id' => 'cs_unknown'], $notFound->parameters());
        }

        self::assertSame(['begin', 'lock order cs_unknown', 'rollback'], $this->journal->entries);
    }

    public function testAMismatchedPaymentIsNeitherRecordedNorInvoiced(): void
    {
        $this->gateway->payments['cs_1'] = SalesExamples::payment($this->order, cents: 100);

        try {
            $this->confirm('cs_1');
            self::fail('A mismatched payment was confirmed.');
        } catch (PaymentMismatch) {
        }

        self::assertSame(['begin', 'lock order cs_1', 'details cs_1', 'rollback'], $this->journal->entries);
        self::assertSame(OrderStatus::Pending, $this->order->status());
        self::assertSame([], $this->invoices->invoices);
        self::assertSame([], $this->mails->sent);
    }

    private function confirm(string $sessionId, string $now = '2026-11-10 10:06:00'): void
    {
        $transaction = new RecordingTransaction($this->journal);

        new ConfirmPaymentHandler(
            $this->orders,
            $this->invoices,
            $this->sequences,
            self::settings(),
            $this->gateway,
            $this->mails,
            new MockClock($now),
            $transaction,
            $transaction,
        )(new ConfirmPayment($sessionId));
    }

    private static function settings(): SettingsRepository
    {
        $settings = Settings::initial();
        $settings->editLegalIdentity(new LegalIdentity('Louis Injust', 'EI', '443061841', '1 rue du Son', 'louis@injust.test', '+33600000000', 'Louis', 'Médiateur', 'https://mediator.test', 'OVH', 'Roubaix', '+33972101007'));
        $settings->editGeneral('', null, new VatMentions('Franchise', 'Reverse charge', 'Outside the EU'), new LocalizedText(), new LocalizedText(), false);

        return new readonly class($settings) implements SettingsRepository {
            public function __construct(private Settings $settings)
            {
            }

            public function current(): Settings
            {
                return $this->settings;
            }

            public function save(Settings $settings): void
            {
            }
        };
    }
}
