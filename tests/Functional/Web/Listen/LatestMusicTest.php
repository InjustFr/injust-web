<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\Listen;

use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Tests\Functional\Web\ConfiguresSite;
use App\Tests\Support\FakePlatforms;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class LatestMusicTest extends WebTestCase
{
    use ConfiguresSite;
    use ListensToReleases;

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00'));
    }

    public function testTheFeaturedReleaseOpensItsListenPage(): void
    {
        $client = self::createClient();
        self::release('Older', links: [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/older')]);
        self::feature(self::release(upc: FakePlatforms::EVERYWHERE, smartLinkUrl: 'https://ffm.to/released'));

        self::assertSame('/listen/white-men-are-the-issue', self::follow($client, '/latest-music'));
        self::assertSame(['no-store, private', 'noindex, nofollow'], [$client->getResponse()->headers->get('Cache-Control'), $client->getResponse()->headers->get('X-Robots-Tag')]);
    }

    public function testAnUpcomingFeaturedReleaseOpensItsPreSave(): void
    {
        $client = self::createClient();
        self::feature(self::release('We are fucked', upc: FakePlatforms::EVERYWHERE, date: '2026-12-04', smartLinkUrl: 'https://ffm.to/wearefucked'));

        self::assertSame('https://ffm.to/wearefucked', self::follow($client, '/latest-music'));
    }

    public function testAFeaturedReleaseWithOnlyASmartLinkOpensIt(): void
    {
        $client = self::createClient();
        self::feature(self::release(smartLinkUrl: 'https://ffm.to/released'));

        self::assertSame('https://ffm.to/released', self::follow($client, '/latest-music'));
    }

    public function testAnUpcomingReleaseWithoutPreSaveOpensItsListenPage(): void
    {
        $client = self::createClient();
        self::feature(self::release('We are fucked', date: '2026-12-04'));

        self::assertSame('/listen/we-are-fucked', self::follow($client, '/latest-music'));
    }

    public function testWithoutAFeaturedReleaseItOpensTheHomePage(): void
    {
        $client = self::createClient();
        self::release(smartLinkUrl: 'https://ffm.to/released');

        self::assertSame('/', self::follow($client, '/latest-music'));
    }

    private static function feature(string $id): void
    {
        self::getContainer()->get(FeatureReleaseHandler::class)(new FeatureRelease(Ulid::fromString($id), true));
    }

    private static function follow(KernelBrowser $client, string $path): ?string
    {
        $client->request('GET', $path);
        self::assertResponseStatusCodeSame(302);

        return $client->getResponse()->headers->get('Location');
    }
}
