<?php

declare(strict_types=1);

namespace App\Tests\Functional\Persistence\Sales;

use App\Domain\Sales\Invoice;
use App\Domain\Sales\Seller;
use App\Domain\Sales\VatCase;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\VatMentions;
use App\Infrastructure\Persistence\Doctrine\DoctrineTransaction;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineInvoiceRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineInvoiceSequenceRepository;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\TransactionRequiredException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class InvoicePersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineTransaction $transaction;

    private DoctrineInvoiceSequenceRepository $sequences;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->transaction = new DoctrineTransaction($this->entityManager);
        $this->sequences = new DoctrineInvoiceSequenceRepository($this->entityManager);
    }

    public function testNumbersAreTakenWithoutGapsAndEachYearHasItsOwnSequence(): void
    {
        self::assertSame(['2026-0001', '2026-0002', '2027-0001', '2026-0003'], [$this->take(2026), $this->take(2026), $this->take(2027), $this->take(2026)]);
    }

    public function testTheLockedRowIsReadAgainSoANumberTakenElsewhereIsNeverReused(): void
    {
        $this->take(2026);
        $this->entityManager->getConnection()->executeStatement('UPDATE invoice_sequence SET last = 41 WHERE year = 2026');

        self::assertSame('2026-0042', $this->take(2026));
    }

    public function testTheSequenceRowIsLockedSoTakingANumberNeedsATransaction(): void
    {
        $this->take(2026);

        $this->expectException(TransactionRequiredException::class);

        $this->sequences->lock(2026);
    }

    public function testTheSequenceRowIsLockedForUpdate(): void
    {
        $connection = $this->entityManager->getConnection();
        $sql = '';
        $this->transaction->atomically(function () use ($connection, &$sql): void {
            $this->sequences->lock(2026);
            $sql = $connection->fetchOne("SELECT string_agg(mode, ',') FROM pg_locks WHERE pid = pg_backend_pid() AND relation = 'invoice_sequence'::regclass");
        });

        self::assertIsString($sql);
        self::assertStringContainsString('RowShareLock', $sql);
    }

    public function testAnInvoiceIsStoredWithItsSnapshots(): void
    {
        $customer = SalesExamples::customer('US', null, 'Riot Inc');
        $order = SalesExamples::paid(SalesExamples::production(12000), $customer);
        $seller = Seller::of(new LegalIdentity('Louis Injust', 'EI', '443061841', '1 rue du Son', 'louis@injust.test', '+33600000000', 'Louis', 'Médiateur', 'https://mediator.test', 'OVH', 'Roubaix', '+33972101007'));
        $invoice = Invoice::issue('2026-0001', $order, $seller, new VatMentions('Franchise', 'Autoliquidation', 'Hors UE'), new \DateTimeImmutable('2026-11-10 10:06:00'));
        $invoices = new DoctrineInvoiceRepository($this->entityManager);
        $invoices->add($invoice);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $stored = $invoices->forOrder($order->id());

        self::assertNotNull($stored);
        self::assertNotSame($invoice, $stored);
        self::assertSame(['2026-0001', $order->reference(), VatCase::NonEuBusiness, 'Hors UE', 12000, 'card'], [$stored->number(), $stored->orderReference(), $stored->vatCase(), $stored->vatMention(), $stored->total()->cents, $stored->paymentMethod()]);
        self::assertEquals($seller, $stored->seller());
        self::assertEquals($customer, $stored->buyer());
        self::assertEquals($invoice->lines(), $stored->lines());
        self::assertSame($invoice->issuedAt()->getTimestamp(), $stored->issuedAt()->getTimestamp());
        self::assertSame($invoice->paidAt()->getTimestamp(), $stored->paidAt()->getTimestamp());
        self::assertNull($invoices->forOrder(new Ulid()));
    }

    private function take(int $year): string
    {
        return $this->transaction->atomically(fn (): string => $this->sequences->lock($year)->next());
    }
}
