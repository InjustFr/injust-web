<?php

declare(strict_types=1);

namespace App\Tests\Functional\Console;

use App\Application\Transaction;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Tester\CommandTester;

final class SalesPurgeTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testAbandonedCheckoutsAreDeletedThirtyDaysAfterTheyStarted(): void
    {
        self::bootKernel();
        $old = self::store(self::expired('2026-10-11 09:59:59'));
        $limit = self::store(self::expired('2026-10-11 10:00:00'));
        $pending = self::store(SalesExamples::order(at: '2026-09-01 10:00:00'));
        $paid = self::store(SalesExamples::paid(paidAt: '2026-09-01 10:00:00'));
        Clock::set(new MockClock('2026-11-10 10:00:00'));

        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:sales:purge'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $left = array_map(static fn (Order $order): string => $order->reference(), $entityManager->getRepository(Order::class)->findAll());
        sort($left);
        $kept = [$limit->reference(), $pending->reference(), $paid->reference()];
        sort($kept);
        self::assertSame($kept, $left);
        self::assertNotContains($old->reference(), $left);
    }

    private static function expired(string $at): Order
    {
        $order = SalesExamples::order(at: $at);
        $order->expire();

        return $order;
    }

    private static function store(Order $order): Order
    {
        self::getContainer()->get(OrderRepository::class)->add($order);
        self::getContainer()->get(Transaction::class)->commit();

        return $order;
    }
}
