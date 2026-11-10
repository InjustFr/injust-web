<?php

declare(strict_types=1);

namespace App\Tests\Functional\Console;

use App\Application\Music\MusicQueries;
use App\Application\Music\ReleaseView;
use App\Tests\Functional\Web\Listen\ListensToReleases;
use App\Tests\Support\FakePlatforms;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Tester\CommandTester;

final class RefreshLinksTest extends KernelTestCase
{
    use ListensToReleases;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testEveryDueReleaseIsRefreshedAndReported(): void
    {
        self::bootKernel();
        Clock::set(new MockClock('2026-11-10 09:00:00 UTC'));
        self::release('White Men are the Issue', FakePlatforms::EVERYWHERE);
        self::release('Partial', FakePlatforms::PARTIAL, date: '2026-12-04');
        self::release('Failing', FakePlatforms::FAILING, date: '2020-01-01');
        self::release('No barcode');
        $old = self::release('Old and complete', '4006381333931', date: '2020-01-01');
        self::refresh($old);
        Clock::set(new MockClock('2026-11-12 09:00:00 UTC'));

        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:music:refresh-links'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(
            "white-men-are-the-issue: spotify found, apple-music found, deezer found, tidal found\n"
            ."partial: spotify notFound, apple-music found, deezer found, tidal notFound\n"
            ."failing: spotify failed, apple-music notFound, deezer failed, tidal notFound\n",
            $tester->getDisplay(),
        );
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $checked = array_map(static fn (ReleaseView $release): ?string => $release->linksCheckedAt, self::getContainer()->get(MusicQueries::class)->releases(new \DateTimeImmutable()));
        self::assertSame(['2026-11-12T09:00:00+00:00', '2026-11-12T09:00:00+00:00', '2026-11-12T09:00:00+00:00', null, '2026-11-10T09:00:00+00:00'], $checked);
    }

    public function testNothingDueSaysSo(): void
    {
        self::bootKernel();

        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:music:refresh-links'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame("No release links to refresh.\n", $tester->getDisplay());
    }
}
