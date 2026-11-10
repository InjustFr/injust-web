<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fixtures;

use App\Application\Music\MusicQueries;
use App\Fixtures\AppFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

final class AppFixturesTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testTheMusicHomeIsFilledWithTheRealCatalogue(): void
    {
        Clock::set(new MockClock('2026-11-10 12:00:00'));
        $container = self::getContainer();
        $container->get(AppFixtures::class)->load($container->get(EntityManagerInterface::class));
        $container->get(EntityManagerInterface::class)->clear();

        $home = $container->get(MusicQueries::class)->home(new \DateTimeImmutable('2026-11-10'));

        self::assertSame(['White Men are the Issue', "Don't tell me who to be", 'This is discrimination', 'We are fucked'], array_map(static fn ($release) => $release->title, $home->releases));
        self::assertSame('We are fucked', $home->featured?->title);
        self::assertSame('2026-12-04', $home->featured->releaseDate);
        self::assertTrue($home->featured->upcoming);
        self::assertCount(9, $home->featured->tracks);
        self::assertSame(['White Men are the Issue', 156], [$home->featured->tracks[0]->title, $home->featured->tracks[0]->durationSeconds]);
        self::assertSame('https://ffm.to/this-is-discrimination', $home->releases[2]->smartLinkUrl);
        self::assertSame([], $home->videos);
        self::assertSame('Fuck the system', $home->text->tagline->en);
        self::assertCount(8, $home->text->hooks);
        self::assertSame(['instagram', 'tiktok', 'youtube', 'spotify', 'linktree'], array_map(static fn ($link) => $link->network, $home->socialLinks));
    }
}
