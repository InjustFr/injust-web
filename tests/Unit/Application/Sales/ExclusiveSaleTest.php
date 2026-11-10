<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingsRepository;
use App\Tests\Support\Sales\FakePaymentGateway;
use App\Tests\Support\Sales\InMemoryBeats;
use App\Tests\Support\Sales\InMemoryInvoices;
use App\Tests\Support\Sales\InMemoryInvoiceSequences;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\RecordingTransaction;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ExclusiveSaleTest extends TestCase
{
    private Journal $journal;

    private InMemoryOrders $orders;

    private InMemoryInvoices $invoices;

    private FakePaymentGateway $gateway;

    private RecordingSalesMails $mails;

    private InMemoryBeats $beats;

    private InMemoryInvoiceSequences $sequences;

    private Beat $beat;

    protected function setUp(): void
    {
        $this->journal = new Journal();
        $this->orders = new InMemoryOrders($this->journal);
        $this->invoices = new InMemoryInvoices();
        $this->gateway = new FakePaymentGateway($this->journal);
        $this->mails = new RecordingSalesMails($this->journal);
        $this->beats = new InMemoryBeats($this->journal);
        $this->sequences = new InMemoryInvoiceSequences($this->journal);
        $this->beat = Beat::add(new BeatDetails('Riot', 'riot', 90, MusicalKey::AMinor, [], true), 0);
        $this->beats->add($this->beat);
    }

    public function testAnExclusiveSaleMarksTheBeatSoldAtThePaymentTimeUnderLock(): void
    {
        $order = $this->sell(true, 'cs_1', '2026-11-10 10:05:00');

        $this->confirm('cs_1');

        $id = $this->beat->id()->toBase32();
        self::assertSame(['begin', 'lock order cs_1', 'details cs_1', 'lock sequence 2026', 'lock beat '.$id, 'end', 'begin', 'lock order cs_1', 'mail '.$order->reference().' 2026-0001', 'commit', 'end'], $this->journal->entries);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:05:00'), $this->beat->soldExclusivelyAt());
        self::assertSame(OrderStatus::Paid, $order->status());
        self::assertNull($order->exclusiveConflictAt());
        self::assertSame([], $this->mails->conflicts);
    }

    public function testANonExclusiveSaleLeavesTheBeatOnSale(): void
    {
        $order = $this->sell(false, 'cs_1');

        $this->confirm('cs_1');

        self::assertNull($this->beat->soldExclusivelyAt());
        self::assertNull($order->exclusiveConflictAt());
        self::assertSame([], $this->mails->conflicts);
        self::assertCount(1, $this->mails->sent);
    }

    public function testASecondExclusiveSaleIsPaidAndInvoicedButFlaggedAndNoticedOnce(): void
    {
        $first = $this->sell(true, 'cs_1', '2026-11-10 10:05:00');
        $second = $this->sell(true, 'cs_2', '2026-11-10 10:05:30');
        $this->confirm('cs_1');
        $this->journal->entries = [];

        $this->confirm('cs_2', '2026-11-10 10:07:00');

        self::assertSame(['begin', 'lock order cs_2', 'details cs_2', 'lock sequence 2026', 'lock beat '.$this->beat->id()->toBase32(), 'end', 'begin', 'lock order cs_2', 'mail '.$second->reference().' 2026-0002', 'commit', 'end', 'begin', 'lock order cs_2', 'mail conflict '.$second->reference(), 'commit', 'end'], $this->journal->entries);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:05:00'), $this->beat->soldExclusivelyAt());
        self::assertSame([OrderStatus::Paid, '2026-0002'], [$second->status(), $second->invoiceNumber()]);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:07:00'), $second->exclusiveConflictAt());
        self::assertNull($first->exclusiveConflictAt());
        self::assertSame([$second->reference()], array_map(static fn ($order): string => $order->reference, $this->mails->conflicts));
        self::assertNotNull($this->mails->conflicts[0]->exclusiveConflictAt);

        $this->journal->entries = [];
        $this->confirm('cs_2', '2026-11-11 10:00:00');

        self::assertSame(['begin', 'lock order cs_2', 'details cs_2', 'end', 'begin', 'lock order cs_2', 'end'], $this->journal->entries);
        self::assertCount(1, $this->mails->conflicts);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:07:00'), $second->exclusiveConflictAt());
    }

    public function testANonExclusiveSalePaidAfterTheExclusiveOneIsFlagged(): void
    {
        $this->beat->markSoldExclusively(new \DateTimeImmutable('2026-11-09 10:00:00'));
        $order = $this->sell(false, 'cs_1');

        $this->confirm('cs_1');

        self::assertSame(OrderStatus::Paid, $order->status());
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00'), $order->exclusiveConflictAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-09 10:00:00'), $this->beat->soldExclusivelyAt());
        self::assertCount(1, $this->mails->conflicts);
    }

    public function testABeatDeletedBeforeThePaymentIsNoExclusiveConflict(): void
    {
        $order = $this->sell(true, 'cs_1');
        $this->beats->remove($this->beat);

        $this->confirm('cs_1');

        self::assertSame(OrderStatus::Paid, $order->status());
        self::assertNull($order->exclusiveConflictAt());
        self::assertSame([], $this->mails->conflicts);
        self::assertCount(1, $this->mails->sent);
    }

    public function testAFailedConflictNoticeIsRetriedWithoutResendingTheConfirmation(): void
    {
        $this->beat->markSoldExclusively(new \DateTimeImmutable('2026-11-09 10:00:00'));
        $order = $this->sell(true, 'cs_1');
        $this->mails->broken = 'conflict';

        try {
            $this->confirm('cs_1');
            self::fail('The notice failure reaches Stripe so it retries.');
        } catch (\RuntimeException) {
        }
        self::assertSame(['begin', 'lock order cs_1', 'mail failed', 'rollback'], \array_slice($this->journal->entries, -4));
        self::assertNotNull($order->confirmationSentAt());
        $this->mails->broken = null;
        $this->journal->entries = [];

        $this->confirm('cs_1');

        self::assertSame(['begin', 'lock order cs_1', 'details cs_1', 'end', 'begin', 'lock order cs_1', 'end', 'begin', 'lock order cs_1', 'mail conflict '.$order->reference(), 'commit', 'end'], $this->journal->entries);
        self::assertCount(1, $this->mails->sent);
        self::assertCount(1, $this->mails->conflicts);
    }

    public function testAFailedConfirmationSendsNoNoticeUntilItGoesOut(): void
    {
        $this->beat->markSoldExclusively(new \DateTimeImmutable('2026-11-09 10:00:00'));
        $this->sell(false, 'cs_1');
        $this->mails->failures = 1;

        try {
            $this->confirm('cs_1');
            self::fail('The confirmation failure reaches Stripe so it retries.');
        } catch (\RuntimeException) {
        }
        self::assertSame([], $this->mails->conflicts);

        $this->confirm('cs_1');

        self::assertCount(1, $this->mails->sent);
        self::assertCount(1, $this->mails->conflicts);
    }

    public function testALessonLocksNoBeat(): void
    {
        $order = SalesExamples::order(SalesExamples::lesson());
        $order->attachSession('cs_1');
        $this->orders->add($order);
        $this->gateway->payments['cs_1'] = SalesExamples::payment($order);

        $this->confirm('cs_1');

        self::assertSame(['begin', 'lock order cs_1', 'details cs_1', 'lock sequence 2026', 'end', 'begin', 'lock order cs_1', 'mail '.$order->reference().' 2026-0001', 'commit', 'end'], $this->journal->entries);
        self::assertNull($order->exclusiveConflictAt());
    }

    private function sell(bool $exclusive, string $session, string $paidAt = '2026-11-10 10:05:00'): Order
    {
        $order = SalesExamples::order(SalesExamples::beat(3000, $exclusive, $this->beat->id()->toBase32()));
        $order->attachSession($session);
        $this->orders->add($order);
        $this->gateway->payments[$session] = SalesExamples::payment($order, paidAt: $paidAt);

        return $order;
    }

    private function confirm(string $sessionId, string $now = '2026-11-10 10:06:00'): void
    {
        $transaction = new RecordingTransaction($this->journal);
        $settings = Settings::initial();
        $settings->editLegalIdentity(new LegalIdentity('Louis Injust', 'EI', '443061841', '1 rue du Son', 'louis@injust.test', '+33600000000', 'Louis', 'Médiateur', 'https://mediator.test', 'OVH', 'Roubaix', '+33972101007'));
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('current')->willReturn($settings);

        new ConfirmPaymentHandler(
            $this->orders,
            $this->invoices,
            $this->sequences,
            $repository,
            $this->gateway,
            $this->mails,
            new MockClock($now),
            $transaction,
            $transaction,
            $this->beats,
        )(new ConfirmPayment($sessionId));
    }
}
