<?php

declare(strict_types=1);

namespace App\Tests\Functional\Persistence\Sales;

use App\Domain\Sales\CreditNote;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceLine;
use App\Domain\Sales\Seller;
use App\Domain\Sales\WithdrawalRequest;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\Price;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineCreditNoteRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineWithdrawalRequestRepository;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class CreditNotePersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineCreditNoteRepository $creditNotes;

    private Invoice $invoice;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->creditNotes = new DoctrineCreditNoteRepository($this->entityManager);
        $this->invoice = Invoice::issue('2026-0001', SalesExamples::paid(SalesExamples::lesson(4500), SalesExamples::customer('BE', 'BE0123456789', 'Riot SRL')), Seller::of(DocumentExamples::identity('FR12443061841')), VatMentions::defaults(), new \DateTimeImmutable('2026-11-10 10:06:00 UTC'));
    }

    public function testACreditNoteIsStoredWithItsSnapshots(): void
    {
        $note = CreditNote::issue('2026-0002', $this->invoice, 1500, 're_1', 'Late cancellation', new \DateTimeImmutable('2026-11-12 09:00:00 UTC'));
        $note->mailed(new \DateTimeImmutable('2026-11-12 09:00:05 UTC'));
        $this->store($note);

        $stored = $this->creditNotes->find($note->id());

        self::assertNotNull($stored);
        self::assertNotSame($note, $stored);
        self::assertSame(['2026-0002', '2026-0001', $this->invoice->orderReference(), 'fr', 1500, 'Autoliquidation', 'Late cancellation', 're_1'], [$stored->number(), $stored->invoiceNumber(), $stored->orderReference(), $stored->locale(), $stored->amount()->cents, $stored->vatMention(), $stored->reason(), $stored->stripeRefundId()]);
        self::assertTrue($this->invoice->orderId()->equals($stored->orderId()));
        self::assertEquals($this->invoice->seller(), $stored->seller());
        self::assertSame('FR12443061841', $stored->seller()->vatNumber);
        self::assertEquals($this->invoice->buyer(), $stored->buyer());
        self::assertEquals([new InvoiceLine('Avoir sur facture 2026-0001 — Cours de guitare', 1, new Price(1500), new Price(1500))], $stored->lines());
        self::assertSame([new \DateTimeImmutable('2026-11-12 09:00:00 UTC')->getTimestamp(), new \DateTimeImmutable('2026-11-12 09:00:05 UTC')->getTimestamp()], [$stored->issuedAt()->getTimestamp(), $stored->mailedAt()?->getTimestamp()]);
        self::assertNull($this->creditNotes->find(new Ulid()));
    }

    public function testCreditNotesAreFoundByRefundOrderAndParisYear(): void
    {
        $first = CreditNote::issue('2026-0002', $this->invoice, 1000, 're_1', null, new \DateTimeImmutable('2026-12-31 22:59:59 UTC'));
        $second = CreditNote::issue('2027-0001', $this->invoice, 1000, 're_2', null, new \DateTimeImmutable('2026-12-31 23:00:00 UTC'));
        $other = CreditNote::issue('2026-0003', DocumentExamples::invoiceOf(SalesExamples::paid()), 1000, 're_3', null, new \DateTimeImmutable('2026-06-01 10:00:00 UTC'));
        array_map($this->store(...), [$second, $first, $other]);

        self::assertSame('2026-0002', $this->creditNotes->byRefund('re_1')?->number());
        self::assertNull($this->creditNotes->byRefund('re_x'));
        self::assertSame(['2026-0002', '2027-0001'], self::numbers(...$this->creditNotes->forOrder($this->invoice->orderId())));
        self::assertSame([], $this->creditNotes->forOrder(new Ulid()));
        self::assertSame(['2026-0003', '2026-0002'], self::numbers(...$this->creditNotes->issuedIn(2026)));
        self::assertSame(['2027-0001'], self::numbers(...$this->creditNotes->issuedIn(2027)));
    }

    public function testARefundIsCreditedOnce(): void
    {
        $this->store(CreditNote::issue('2026-0002', $this->invoice, 1000, 're_1', null, new \DateTimeImmutable()));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->store(CreditNote::issue('2026-0003', $this->invoice, 1000, 're_1', null, new \DateTimeImmutable()));
    }

    public function testAWithdrawalRequestIsStoredOncePerOrder(): void
    {
        $withdrawals = new DoctrineWithdrawalRequestRepository($this->entityManager);
        $order = SalesExamples::paid();
        $request = WithdrawalRequest::receive($order, 'buyer@example.test', 'Test Buyer', 'Changed my mind', 'en', new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));
        $request->acknowledged(new \DateTimeImmutable('2026-11-11 10:00:05 UTC'));
        $withdrawals->add($request);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $stored = $withdrawals->forOrder($order->id());

        self::assertNotNull($stored);
        self::assertSame([$request->id()->toBase32(), $order->reference(), 'buyer@example.test', 'Test Buyer', 'Changed my mind', 'en'], [$stored->id()->toBase32(), $stored->orderReference(), $stored->email(), $stored->name(), $stored->reason(), $stored->locale()]);
        self::assertSame([new \DateTimeImmutable('2026-11-11 10:00:00 UTC')->getTimestamp(), new \DateTimeImmutable('2026-11-11 10:00:05 UTC')->getTimestamp()], [$stored->receivedAt()->getTimestamp(), $stored->acknowledgedAt()?->getTimestamp()]);
        self::assertNull($withdrawals->forOrder(new Ulid()));

        $withdrawals->add(WithdrawalRequest::receive($order, 'buyer@example.test', null, null, 'fr', new \DateTimeImmutable()));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    /** @return list<string> */
    private static function numbers(CreditNote ...$notes): array
    {
        return array_values(array_map(static fn (CreditNote $note): string => $note->number(), $notes));
    }

    private function store(CreditNote $note): void
    {
        $this->creditNotes->add($note);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
