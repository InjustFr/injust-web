<?php

declare(strict_types=1);

namespace App\Tests\Functional\Persistence\Sales;

use App\Application\Sales\CountryTotal;
use App\Application\Sales\RevenueLine;
use App\Application\Sales\StoredSalesQueries;
use App\Application\Sales\Totals;
use App\Domain\Sales\CreditNote;
use App\Domain\Sales\Customer;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\Purchasable;
use App\Domain\Sales\Seller;
use App\Domain\Sales\WithdrawalRequest;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\VatMentions;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineCreditNoteRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineInvoiceRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineOrderRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineWithdrawalRequestRepository;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class SalesQueriesTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private StoredSalesQueries $queries;

    /** @var array<string, Invoice> */
    private array $issued = [];

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->queries = new StoredSalesQueries(new DoctrineOrderRepository($this->entityManager), new DoctrineInvoiceRepository($this->entityManager), new DoctrineCreditNoteRepository($this->entityManager), new DoctrineWithdrawalRequestRepository($this->entityManager));
    }

    public function testTheRevenueBookListsThePaidAndRefundedOrdersOfTheParisYearByPaymentDate(): void
    {
        $march = $this->sold(SalesExamples::lesson(4500, 'online'), SalesExamples::customer('DE', name: 'Max'), '2026-03-01 10:00:00 UTC', '2026-0002');
        $february = $this->sold(SalesExamples::production(12000), SalesExamples::customer('FR', name: 'Léa'), '2026-02-01 10:00:00 UTC', '2026-0001');
        $refunded = $this->sold(SalesExamples::lesson(3000, 'online'), SalesExamples::customer('IT', name: 'Gia'), '2026-04-01 10:00:00 UTC', '2026-0003', refund: true);
        $this->sold(SalesExamples::lesson(), SalesExamples::customer(), '2025-12-31 22:59:59 UTC', '2025-0001');
        $this->sold(SalesExamples::lesson(), SalesExamples::customer(), '2026-12-31 23:00:00 UTC', '2027-0001');
        $this->store(SalesExamples::order(at: '2026-05-01 10:00:00'));
        $expired = SalesExamples::order(at: '2026-05-02 10:00:00');
        $expired->expire();
        $this->store($expired);

        self::assertEquals([
            new RevenueLine(new \DateTimeImmutable('2026-02-01 11:00:00', new \DateTimeZone('Europe/Paris')), '2026-0001', $february->reference(), 'Léa', 'FR', 'production', 12000, 'card', 'Facture'),
            new RevenueLine(new \DateTimeImmutable('2026-03-01 11:00:00', new \DateTimeZone('Europe/Paris')), '2026-0002', $march->reference(), 'Max', 'DE', 'lesson', 4500, 'card', 'Facture'),
            new RevenueLine(new \DateTimeImmutable('2026-04-01 12:00:00', new \DateTimeZone('Europe/Paris')), '2026-0003', $refunded->reference(), 'Gia', 'IT', 'lesson', 3000, 'card', 'Facture'),
        ], $this->queries->revenueBook(2026));
        self::assertCount(1, $this->queries->revenueBook(2025));
        self::assertCount(1, $this->queries->revenueBook(2027));
    }

    public function testTheTotalsSplitTheYearByCountryAndTypeAndWatchTheEuThresholds(): void
    {
        $this->sold(SalesExamples::lesson(4500, 'online'), SalesExamples::customer('DE'), '2026-03-01 10:00:00 UTC', '2026-0001');
        $this->sold(SalesExamples::lesson(5500, 'online'), SalesExamples::customer('DE'), '2026-03-02 10:00:00 UTC', '2026-0002');
        $this->sold(SalesExamples::lesson(6000, 'at-home'), SalesExamples::customer('DE'), '2026-03-03 10:00:00 UTC', '2026-0003');
        $this->sold(SalesExamples::lesson(4000, 'online'), SalesExamples::customer('BE', 'BE0123456789'), '2026-03-04 10:00:00 UTC', '2026-0004');
        $this->sold(SalesExamples::lesson(4000, 'online'), SalesExamples::customer('FR'), '2026-03-05 10:00:00 UTC', '2026-0005');
        $this->sold(SalesExamples::lesson(4000, 'online'), SalesExamples::customer('US'), '2026-03-06 10:00:00 UTC', '2026-0006');
        $this->sold(SalesExamples::beat(3000), SalesExamples::customer('ES'), '2026-03-07 10:00:00 UTC', '2026-0007');
        $this->sold(SalesExamples::beat(2500), SalesExamples::customer('FR'), '2026-03-08 10:00:00 UTC', '2026-0008');
        $this->sold(SalesExamples::beat(2500), SalesExamples::customer('NL', 'NL123456789B01'), '2026-03-09 10:00:00 UTC', '2026-0009');
        $this->sold(SalesExamples::production(12000), SalesExamples::customer('ES'), '2026-03-10 10:00:00 UTC', '2026-0010');
        $this->sold(SalesExamples::beat(9900), SalesExamples::customer('ES'), '2025-06-01 10:00:00 UTC', '2025-0001');

        self::assertEquals(new Totals([
            new CountryTotal('BE', 'lesson', 4000, 1),
            new CountryTotal('DE', 'lesson', 16000, 3),
            new CountryTotal('ES', 'beat', 3000, 1),
            new CountryTotal('ES', 'production', 12000, 1),
            new CountryTotal('FR', 'beat', 2500, 1),
            new CountryTotal('FR', 'lesson', 4000, 1),
            new CountryTotal('NL', 'beat', 2500, 1),
            new CountryTotal('US', 'lesson', 4000, 1),
        ], 3000, 10000), $this->queries->totals(2026));
        self::assertEquals(new Totals([], 0, 0), $this->queries->totals(2024));
    }

    public function testTheRevenueBookSubtractsEachCreditNoteOnItsIssueDate(): void
    {
        $withdrawn = $this->sold(SalesExamples::lesson(4500, 'online'), SalesExamples::customer('DE', name: 'Max'), '2026-03-01 10:00:00 UTC', '2026-0003', withdrawnAt: '2026-03-02 10:00:00 UTC');
        $lastYear = $this->sold(SalesExamples::production(12000), SalesExamples::customer('FR', name: 'Léa'), '2025-12-20 10:00:00 UTC', '2025-0009');
        $this->credit($withdrawn, '2026-0004', 4500, '2026-03-05 10:00:00 UTC');
        $this->credit($lastYear, '2026-0001', 3000, '2026-01-10 10:00:00 UTC');

        self::assertEquals([
            new RevenueLine(new \DateTimeImmutable('2026-01-10 11:00:00', new \DateTimeZone('Europe/Paris')), '2026-0001', $lastYear->reference(), 'Léa', 'FR', 'production', -3000, 'card', 'Avoir'),
            new RevenueLine(new \DateTimeImmutable('2026-03-01 11:00:00', new \DateTimeZone('Europe/Paris')), '2026-0003', $withdrawn->reference(), 'Max', 'DE', 'lesson', 4500, 'card', 'Facture'),
            new RevenueLine(new \DateTimeImmutable('2026-03-05 11:00:00', new \DateTimeZone('Europe/Paris')), '2026-0004', $withdrawn->reference(), 'Max', 'DE', 'lesson', -4500, 'card', 'Avoir'),
        ], $this->queries->revenueBook(2026));
        self::assertEquals([
            new RevenueLine(new \DateTimeImmutable('2025-12-20 11:00:00', new \DateTimeZone('Europe/Paris')), '2025-0009', $lastYear->reference(), 'Léa', 'FR', 'production', 12000, 'card', 'Facture'),
        ], $this->queries->revenueBook(2025));
    }

    public function testTheTotalsAreNetOfTheCreditNotesOfTheYear(): void
    {
        $online = $this->sold(SalesExamples::lesson(4500, 'online'), SalesExamples::customer('DE'), '2026-03-01 10:00:00 UTC', '2026-0001');
        $this->sold(SalesExamples::lesson(5500, 'online'), SalesExamples::customer('DE'), '2026-03-02 10:00:00 UTC', '2026-0002');
        $beat = $this->sold(SalesExamples::beat(3000), SalesExamples::customer('ES'), '2026-03-03 10:00:00 UTC', '2026-0003');
        $lastYear = $this->sold(SalesExamples::production(12000), SalesExamples::customer('FR'), '2025-12-20 10:00:00 UTC', '2025-0009');
        $this->credit($online, '2026-0004', 1500, '2026-03-05 10:00:00 UTC');
        $this->credit($beat, '2026-0005', 3000, '2026-03-06 10:00:00 UTC');
        $this->credit($lastYear, '2026-0006', 2000, '2026-01-10 10:00:00 UTC');
        $this->credit($online, '2027-0001', 1000, '2027-01-10 10:00:00 UTC');

        self::assertEquals(new Totals([
            new CountryTotal('DE', 'lesson', 8500, 2),
            new CountryTotal('ES', 'beat', 0, 1),
            new CountryTotal('FR', 'production', -2000, 0),
        ], 0, 8500), $this->queries->totals(2026));
        self::assertEquals(new Totals([new CountryTotal('DE', 'lesson', -1000, 0)], 0, -1000), $this->queries->totals(2027));
    }

    public function testTheOrderShowsItsRefundsWithdrawalAndCreditNotes(): void
    {
        $order = $this->sold(SalesExamples::lesson(4500), SalesExamples::customer('FR'), '2026-03-01 10:00:00 UTC', '2026-0001', withdrawnAt: '2026-03-02 10:00:00 UTC');
        $request = WithdrawalRequest::receive($order, 'stripe@example.test', 'Test Buyer', 'Changed my mind', 'en', new \DateTimeImmutable('2026-03-02 10:00:00 UTC'));
        new DoctrineWithdrawalRequestRepository($this->entityManager)->add($request);
        $note = $this->credit($order, '2026-0002', 1500, '2026-03-05 10:00:00 UTC');
        $this->credit($order, '2026-0003', 3000, '2026-03-06 10:00:00 UTC');

        $view = $this->queries->order($order->id()->toBase32());
        $creditNote = $this->queries->creditNote($note->id()->toBase32());

        self::assertNotNull($view);
        self::assertSame([4500, 'refunded'], [$view->refundedCents, $view->status]);
        self::assertSame(new \DateTimeImmutable('2026-03-02 10:00:00 UTC')->getTimestamp(), $view->withdrawnAt?->getTimestamp());
        self::assertSame('2026-03-16', $view->withdrawalLastDay?->format('Y-m-d'));
        self::assertSame([$request->id()->toBase32(), 'Test Buyer', 'Changed my mind', null], [$view->withdrawal?->id, $view->withdrawal?->name, $view->withdrawal?->reason, $view->withdrawal?->acknowledgedAt]);
        self::assertSame(['2026-0002', '2026-0003'], array_map(static fn ($note): string => $note->number, $view->creditNotes));
        self::assertNotNull($creditNote);
        self::assertSame([$note->id()->toBase32(), '2026-0002', '2026-0001', $order->id()->toBase32(), $order->reference(), 1500, 're_2026-0002', null], [$creditNote->id, $creditNote->number, $creditNote->invoiceNumber, $creditNote->orderId, $creditNote->orderReference, $creditNote->amount->cents, $creditNote->stripeRefundId, $creditNote->reason]);
        self::assertNull($this->queries->creditNote((string) new Ulid()));
        self::assertNull($this->queries->creditNote('nope'));
    }

    public function testTheOrderListLeavesTheWithdrawalAndCreditNotesOut(): void
    {
        $order = $this->sold(SalesExamples::lesson(4500), SalesExamples::customer('FR'), '2026-03-01 10:00:00 UTC', '2026-0001');
        $this->credit($order, '2026-0002', 1500, '2026-03-05 10:00:00 UTC');

        $listed = $this->queries->orders()[0];

        self::assertSame([1500, null, []], [$listed->refundedCents, $listed->withdrawal, $listed->creditNotes]);
    }

    public function testTheOrdersAreListedWithTheirDetails(): void
    {
        $customer = SalesExamples::customer('BE', 'BE0123456789', 'Riot SRL');
        $paid = $this->sold(SalesExamples::lesson(4500, 'online'), $customer, '2026-03-01 10:00:00 UTC', '2026-0001');
        $pending = SalesExamples::order(at: '2026-03-02 10:00:00 UTC');
        $this->store($pending);

        self::assertSame([$pending->reference(), $paid->reference()], array_map(static fn ($order): string => $order->reference, $this->queries->orders()));
        self::assertSame([$paid->reference()], array_map(static fn ($order): string => $order->reference, $this->queries->orders(OrderStatus::Paid, 2026)));
        self::assertSame([], $this->queries->orders(null, 2025));

        $view = $this->queries->order($paid->id()->toBase32());
        self::assertNotNull($view);
        self::assertSame([$paid->id()->toBase32(), $paid->reference(), 'lesson', '01JCLESSON0000000000000000', 'Cours de guitare', 'online', 4500, 'fr', 'stripe@example.test', 3, 'paid', '2026-0001', 'cs_'.$paid->reference(), 'pi_'.$paid->reference()], [$view->id, $view->reference, $view->productType, $view->productId, $view->productLabel, $view->productFormat, $view->price->cents, $view->locale, $view->email, $view->cgvVersion, $view->status, $view->invoiceNumber, $view->checkoutSessionId, $view->paymentIntentId]);
        self::assertEquals($paid->consents(), $view->consents);
        self::assertEquals($customer, $view->customer);
        self::assertSame($paid->createdAt()->getTimestamp(), $view->createdAt->getTimestamp());
        self::assertSame($paid->paidAt()?->getTimestamp(), $view->paidAt?->getTimestamp());
        self::assertNull($view->confirmationSentAt);
        self::assertNull($this->queries->order('nope'));
        self::assertNull($this->queries->order((string) new Ulid()));
    }

    public function testTheInvoiceOfAnOrderIsShownAsIssued(): void
    {
        $order = $this->sold(SalesExamples::lesson(4500), SalesExamples::customer('FR'), '2026-03-01 10:00:00 UTC', '2026-0001');
        $seller = Seller::of(LegalIdentity::blank());
        $invoices = new DoctrineInvoiceRepository($this->entityManager);
        $invoices->add(Invoice::issue('2026-0001', $order, $seller, VatMentions::defaults(), new \DateTimeImmutable('2026-03-01 10:01:00 UTC')));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $view = $this->queries->invoice($order->id()->toBase32());

        self::assertNotNull($view);
        self::assertSame(['2026-0001', $order->id()->toBase32(), $order->reference(), 'fr', 'consumer', 'TVA non applicable – art. 293 B du CGI', 4500, 'card', null], [$view->number, $view->orderId, $view->orderReference, $view->locale, $view->vatCase, $view->vatMention, $view->total->cents, $view->paymentMethod, $view->creditedInvoiceNumber]);
        self::assertEquals($seller, $view->seller);
        self::assertEquals(SalesExamples::customer('FR'), $view->buyer);
        self::assertSame('Cours de guitare', $view->lines[0]->label);
        self::assertSame(new \DateTimeImmutable('2026-03-01 10:01:00 UTC')->getTimestamp(), $view->issuedAt->getTimestamp());
        self::assertSame(new \DateTimeImmutable('2026-03-01 10:00:00 UTC')->getTimestamp(), $view->paidAt->getTimestamp());
        self::assertNotEmpty($view->id);
        self::assertNull($this->queries->invoice((string) new Ulid()));
        self::assertNull($this->queries->invoice('nope'));
    }

    private function credit(Order $order, string $number, int $cents, string $issuedAt): CreditNote
    {
        new DoctrineOrderRepository($this->entityManager)->get($order->id())->refund($cents);
        $note = CreditNote::issue($number, $this->issued[$order->reference()], $cents, 're_'.$number, null, new \DateTimeImmutable($issuedAt));
        new DoctrineCreditNoteRepository($this->entityManager)->add($note);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $note;
    }

    private function sold(Purchasable $product, Customer $customer, string $paidAt, string $invoiceNumber, bool $refund = false, ?string $withdrawnAt = null): Order
    {
        $order = SalesExamples::order($product, new \DateTimeImmutable($paidAt)->modify('-10 minutes')->format('Y-m-d H:i:s e'));
        $order->attachSession('cs_'.$order->reference());
        $order->pay(SalesExamples::payment($order, $customer, $paidAt));
        $order->invoiced($invoiceNumber);
        $this->issued[$order->reference()] = Invoice::issue($invoiceNumber, $order, Seller::of(LegalIdentity::blank()), VatMentions::defaults(), new \DateTimeImmutable($paidAt));
        if (null !== $withdrawnAt) {
            $order->withdraw(new \DateTimeImmutable($withdrawnAt));
        }
        if ($refund) {
            $order->refund($order->price()->cents);
        }
        $this->store($order);

        return $order;
    }

    private function store(Order $order): void
    {
        new DoctrineOrderRepository($this->entityManager)->add($order);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
