<?php

declare(strict_types=1);

namespace App\Tests\Functional\Persistence\Sales;

use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Shared\Exception\NotFound;
use App\Infrastructure\Persistence\Doctrine\DoctrineTransaction;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineOrderRepository;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\TransactionRequiredException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class OrderPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineOrderRepository $orders;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->orders = new DoctrineOrderRepository($this->entityManager);
    }

    public function testAnOrderComesBackWithItsConsentsAndCustomer(): void
    {
        $customer = SalesExamples::customer('BE', 'BE0123456789', 'Riot SRL');
        $order = SalesExamples::paid(SalesExamples::lesson(4500, 'online'), $customer, '2026-11-10 10:05:00', 'cs_round');
        $order->invoiced('2026-0001');
        $order->confirmationSent(new \DateTimeImmutable('2026-11-10 10:06:00'));
        $this->store($order);

        $stored = $this->orders->get($order->id());

        self::assertNotSame($order, $stored);
        self::assertSame([$order->reference(), OrderStatus::Paid, 'online', 4500, 'cs_round', 'pi_'.$order->reference(), '2026-0001'], [$stored->reference(), $stored->status(), $stored->productFormat(), $stored->price()->cents, $stored->checkoutSessionId(), $stored->paymentIntentId(), $stored->invoiceNumber()]);
        self::assertEquals($order->consents(), $stored->consents());
        self::assertEquals($customer, $stored->customer());
        self::assertSame([3, 'CGV v3', 'Terms v3', $order->terms()->publishedAt->getTimestamp()], [$stored->terms()->version, $stored->terms()->body->fr, $stored->terms()->body->en, $stored->terms()->publishedAt->getTimestamp()]);
        self::assertSame($order->paidAt()?->getTimestamp(), $stored->paidAt()?->getTimestamp());
        self::assertSame($order->createdAt()->getTimestamp(), $stored->createdAt()->getTimestamp());
        self::assertSame(new \DateTimeImmutable('2026-11-10 10:06:00')->getTimestamp(), $stored->confirmationSentAt()?->getTimestamp());
    }

    public function testAnOrderIsFoundByItsSessionOrItsPayment(): void
    {
        $order = SalesExamples::paid(session: 'cs_find');
        $this->store($order);

        self::assertSame($order->id()->toBase32(), $this->orders->bySession('cs_find')?->id()->toBase32());
        self::assertSame($order->id()->toBase32(), $this->orders->byPaymentIntent('pi_'.$order->reference())?->id()->toBase32());
        self::assertNull($this->orders->bySession('cs_other'));
        self::assertNull($this->orders->byPaymentIntent('pi_other'));
        self::assertNull($this->orders->find(new Ulid()));
    }

    public function testAMissingOrderIsNotFound(): void
    {
        $this->expectException(NotFound::class);

        $this->orders->get(new Ulid());
    }

    public function testTheSessionLookupLocksTheRowSoItNeedsATransaction(): void
    {
        $order = SalesExamples::order();
        $order->attachSession('cs_lock');
        $this->store($order);

        $locked = new DoctrineTransaction($this->entityManager)->atomically(fn () => $this->orders->lockBySession('cs_lock'));

        self::assertSame($order->id()->toBase32(), $locked?->id()->toBase32());
        $this->expectException(TransactionRequiredException::class);
        $this->orders->lockBySession('cs_lock');
    }

    public function testTheLockedOrderIsReadAgainFromTheDatabase(): void
    {
        $order = SalesExamples::order();
        $order->attachSession('cs_fresh');
        $this->orders->add($order);
        $this->entityManager->flush();
        $this->entityManager->getConnection()->executeStatement("UPDATE sales_order SET status = 'expired' WHERE checkout_session_id = 'cs_fresh'");

        $locked = new DoctrineTransaction($this->entityManager)->atomically(fn () => $this->orders->lockBySession('cs_fresh'));

        self::assertSame($order, $locked);
        self::assertSame(OrderStatus::Expired, $locked->status());
    }

    public function testTheRefundAndWithdrawalAreKept(): void
    {
        $order = SalesExamples::paid(paidAt: '2026-11-10 10:05:00 UTC', session: 'cs_withdrawn');
        $order->withdraw(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));
        $order->refund(1500);
        $this->store($order);

        $stored = $this->orders->get($order->id());

        self::assertSame([OrderStatus::Withdrawn, 1500], [$stored->status(), $stored->refundedCents()]);
        self::assertSame(new \DateTimeImmutable('2026-11-11 10:00:00 UTC')->getTimestamp(), $stored->withdrawnAt()?->getTimestamp());
    }

    public function testAnOrderIsFoundAndLockedByItsReferenceOrItsPayment(): void
    {
        $order = SalesExamples::paid(session: 'cs_reference');
        $this->store($order);
        $this->entityManager->getConnection()->executeStatement("UPDATE sales_order SET email = 'changed@example.test' WHERE checkout_session_id = 'cs_reference'");

        self::assertSame($order->id()->toBase32(), $this->orders->byReference($order->reference())?->id()->toBase32());
        self::assertNull($this->orders->byReference('INJ-ZZZZZZ'));
        [$byReference, $byPayment, $missing] = new DoctrineTransaction($this->entityManager)->atomically(fn (): array => [
            $this->orders->lockByReference($order->reference()),
            $this->orders->lockByPaymentIntent('pi_'.$order->reference()),
            $this->orders->lockByPaymentIntent('pi_other'),
        ]);

        self::assertSame([$order->reference(), 'changed@example.test'], [$byReference?->reference(), $byReference?->email()]);
        self::assertSame($byReference, $byPayment);
        self::assertNull($missing);
        $this->expectException(TransactionRequiredException::class);
        $this->orders->lockByReference($order->reference());
    }

    public function testThePaymentLookupLocksTheRowSoItNeedsATransaction(): void
    {
        $order = SalesExamples::paid(session: 'cs_payment_lock');
        $this->store($order);

        $this->expectException(TransactionRequiredException::class);

        $this->orders->lockByPaymentIntent('pi_'.$order->reference());
    }

    public function testOrdersAreListedNewestFirstByStatusAndParisYear(): void
    {
        $old = SalesExamples::order(at: '2025-12-31 22:30:00 UTC');
        $paid = SalesExamples::paid(paidAt: '2026-03-01 10:00:00 UTC', session: 'cs_paid');
        $pending = SalesExamples::order(at: '2026-12-31 22:59:59 UTC');
        $next = SalesExamples::order(at: '2026-12-31 23:00:00 UTC');
        array_map($this->store(...), [$old, $paid, $pending, $next]);

        self::assertSame(self::references($next, $pending, $paid, $old), self::references(...$this->orders->newestFirst(null, null)));
        self::assertSame(self::references($pending, $paid), self::references(...$this->orders->newestFirst(null, 2026)));
        self::assertSame(self::references($next, $pending, $old), self::references(...$this->orders->newestFirst(OrderStatus::Pending, null)));
        self::assertSame(self::references($paid), self::references(...$this->orders->newestFirst(OrderStatus::Paid, 2026)));
        self::assertSame(self::references($old), self::references(...$this->orders->newestFirst(null, 2025)));
    }

    /** @return list<string> */
    private static function references(Order ...$orders): array
    {
        return array_values(array_map(static fn (Order $order): string => $order->reference(), $orders));
    }

    private function store(Order $order): void
    {
        $this->orders->add($order);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
