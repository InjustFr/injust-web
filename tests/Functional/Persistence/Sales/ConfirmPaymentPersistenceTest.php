<?php

declare(strict_types=1);

namespace App\Tests\Functional\Persistence\Sales;

use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceSequence;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Persistence\Doctrine\DoctrineTransaction;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineInvoiceRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineInvoiceSequenceRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineOrderRepository;
use App\Tests\Support\Sales\FakePaymentGateway;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class ConfirmPaymentPersistenceTest extends KernelTestCase
{
    public function testAPaymentIsStoredWithItsInvoiceAndAReplayAddsNothing(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $orders = new DoctrineOrderRepository($entityManager);
        $order = SalesExamples::order();
        $order->attachSession('cs_db');
        $orders->add($order);
        $entityManager->flush();
        $gateway = new FakePaymentGateway();
        $gateway->payments['cs_db'] = SalesExamples::payment($order);
        $mails = new RecordingSalesMails();
        $mails->failures = 1;
        $confirm = static function (EntityManagerInterface $entityManager) use ($gateway, $mails): void {
            $transaction = new DoctrineTransaction($entityManager);
            new ConfirmPaymentHandler(new DoctrineOrderRepository($entityManager), new DoctrineInvoiceRepository($entityManager), new DoctrineInvoiceSequenceRepository($entityManager), self::getContainer()->get(SettingsRepository::class), $gateway, $mails, new MockClock('2026-11-10 10:06:00'), $transaction, $transaction)(new ConfirmPayment('cs_db'));
        };

        try {
            $confirm($entityManager);
            self::fail('The mail failure was swallowed.');
        } catch (\RuntimeException) {
        }
        self::assertFalse($entityManager->isOpen());
        $entityManager = self::getContainer()->get(ManagerRegistry::class)->resetManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $confirm($entityManager);
        $entityManager->clear();
        $confirm($entityManager);
        $entityManager->clear();

        $stored = $entityManager->find(Order::class, $order->id());
        self::assertSame([OrderStatus::Paid, '2026-0001'], [$stored?->status(), $stored?->invoiceNumber()]);
        self::assertNotNull($stored?->confirmationSentAt());
        self::assertCount(1, $mails->sent);
        self::assertCount(1, $entityManager->getRepository(Invoice::class)->findAll());
        self::assertSame(1, $entityManager->find(InvoiceSequence::class, 2026)?->last());
    }
}
