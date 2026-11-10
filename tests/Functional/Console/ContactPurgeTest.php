<?php

declare(strict_types=1);

namespace App\Tests\Functional\Console;

use App\Application\Contact\ContactQueries;
use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\SendContactMessage\SendContactMessageHandler;
use App\Domain\Contact\ContactTopic;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Tester\CommandTester;

final class ContactPurgeTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testMessagesOlderThanThreeYearsAreDeleted(): void
    {
        self::bootKernel();
        self::receive('Expired', '2026-11-10 09:59:59');
        self::receive('Limit', '2026-11-10 10:00:00');
        self::receive('Recent', '2029-11-01 10:00:00');
        Clock::set(new MockClock('2029-11-10 10:00:00'));

        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:contact:purge'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(['Recent', 'Limit'], array_map(static fn ($message): string => $message->name, self::getContainer()->get(ContactQueries::class)->messages(true)));
    }

    private static function receive(string $name, string $at): void
    {
        Clock::set(new MockClock($at));
        self::getContainer()->get(SendContactMessageHandler::class)(new SendContactMessage($name, 'joe@example.test', ContactTopic::General, 'Hello there, Injust.', 'en'));
    }
}
