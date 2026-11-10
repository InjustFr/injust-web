<?php

declare(strict_types=1);

namespace App\Tests\Functional\Persistence\Sales;

use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Sales\Order;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Persistence\Doctrine\Beats\DoctrineBeatRepository;
use App\Infrastructure\Persistence\Doctrine\DoctrineTransaction;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineInvoiceRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineInvoiceSequenceRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineOrderRepository;
use App\Tests\Support\Sales\FakePaymentGateway;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class BeatSalePersistenceTest extends KernelTestCase
{
    public function testTheLicenceSnapshotAndTheConflictAreStored(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $order = SalesExamples::paid(SalesExamples::beat(4900, true));
        $order->flagExclusiveConflict(new \DateTimeImmutable('2026-11-10 10:06:00 UTC'));
        $entityManager->persist($order);
        $entityManager->flush();
        $entityManager->clear();

        $stored = $entityManager->find(Order::class, $order->id());

        self::assertEquals($order->licence(), $stored?->licence());
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00 UTC'), $stored?->exclusiveConflictAt());
        self::assertNull(SalesExamples::order()->licence());
    }

    public function testAnExclusiveSaleIsStoredOnTheLockedBeat(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $beat = Beat::add(new BeatDetails('Riot', 'riot', 90, MusicalKey::AMinor, [], true), 0);
        $entityManager->persist($beat);
        $order = SalesExamples::order(SalesExamples::beat(49900, true, $beat->id()->toBase32()));
        $order->attachSession('cs_beat');
        $entityManager->persist($order);
        $entityManager->flush();
        $gateway = new FakePaymentGateway();
        $gateway->payments['cs_beat'] = SalesExamples::payment($order, paidAt: '2026-11-10 10:05:00 UTC');
        $transaction = new DoctrineTransaction($entityManager);

        new ConfirmPaymentHandler(new DoctrineOrderRepository($entityManager), new DoctrineInvoiceRepository($entityManager), new DoctrineInvoiceSequenceRepository($entityManager), self::getContainer()->get(SettingsRepository::class), $gateway, new RecordingSalesMails(), new MockClock('2026-11-10 10:06:00'), $transaction, $transaction, new DoctrineBeatRepository($entityManager))(new ConfirmPayment('cs_beat'));
        $entityManager->clear();

        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:05:00 UTC'), $entityManager->find(Beat::class, $beat->id())?->soldExclusivelyAt());
        self::assertNull($entityManager->find(Order::class, $order->id())?->exclusiveConflictAt());
    }
}
