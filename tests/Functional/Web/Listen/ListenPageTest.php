<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\Listen;

use App\Application\Music\MusicQueries;
use App\Application\Music\UploadReleaseCover\UploadReleaseCover;
use App\Application\Music\UploadReleaseCover\UploadReleaseCoverHandler;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\ReleaseType;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Functional\Web\ConfiguresSite;
use App\Tests\Support\FakePlatforms;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Ulid;

final class ListenPageTest extends WebTestCase
{
    use ConfiguresSite;
    use ListensToReleases;

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00'));
    }

    public function testAReleasedSingleListsOneButtonPerPlatformInTheFixedOrder(): void
    {
        $client = self::createClient();
        self::editGeneral(searchIndexing: true);
        $id = self::release(upc: FakePlatforms::EVERYWHERE, links: [
            new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/white-men'),
            new PlatformLink(Platform::Youtube, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        ]);
        self::refresh($id);
        self::getContainer()->get(UploadReleaseCoverHandler::class)(new UploadReleaseCover(Ulid::fromString($id), \dirname(__DIR__, 3).'/Fixtures/large-fern.png'));

        $crawler = $client->request('GET', '/listen/white-men-are-the-issue');

        self::assertResponseIsSuccessful();
        self::assertSame('White Men are the Issue | Injust', $crawler->filter('title')->text());
        self::assertSame('White Men are the Issue', $crawler->filter('main h1')->text());
        self::assertSame('Injust · Single · 2026', $crawler->filter('.listen-meta')->text());
        $cover = $crawler->filter('.listen-cover img');
        self::assertMatchesRegularExpression('#^/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', (string) $cover->attr('src'));
        self::assertSame(['high', ''], [$cover->attr('fetchpriority'), $cover->attr('alt')]);
        self::assertNotNull($cover->attr('width'));
        self::assertNotNull($cover->attr('height'));
        $buttons = $crawler->filter('.listen-links a');
        self::assertSame(
            ['/listen/white-men-are-the-issue/spotify', '/listen/white-men-are-the-issue/apple-music', '/listen/white-men-are-the-issue/deezer', '/listen/white-men-are-the-issue/tidal', '/listen/white-men-are-the-issue/youtube', '/listen/white-men-are-the-issue/bandcamp'],
            $buttons->each(static fn (Crawler $button): ?string => $button->attr('href')),
        );
        self::assertSame(['Spotify', 'Apple Music', 'Deezer', 'TIDAL', 'YouTube', 'Bandcamp'], $buttons->filter('.listen-platform')->each(static fn (Crawler $name): string => $name->text()));
        self::assertSame(['Play', 'Play', 'Play', 'Play', 'Watch', 'Play'], $buttons->filter('.listen-cta')->each(static fn (Crawler $cta): string => $cta->text()));
        self::assertCount(6, $buttons->filter('svg[aria-hidden="true"]'));
        self::assertSame('Listen on', $crawler->filter('#listen-on')->text());
        self::assertCount(0, $crawler->filter('[data-island="Countdown"], .listen-presave, .listen-soon'));
        self::assertSame('/', $crawler->filter('.listen-more')->attr('href'));
        self::assertSame('More from Injust', $crawler->filter('.listen-more')->text());
        self::assertSame('music.song', $crawler->filter('meta[property="og:type"]')->attr('content'));
        self::assertSame('http://localhost'.$cover->attr('src'), $crawler->filter('meta[property="og:image"]')->attr('content'));
        self::assertSame('http://localhost/listen/white-men-are-the-issue', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('http://localhost/fr/listen/white-men-are-the-issue', $crawler->filter('link[hreflang="fr"]')->attr('href'));
        self::assertSame('index, follow, max-image-preview:large', $crawler->filter('meta[name="robots"]')->attr('content'));
        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertSame('BreadcrumbList', Json::string($graph, 1, '@type'));
        self::assertSame('MusicRecording', Json::string($graph, 2, '@type'));
        self::assertSame(['https://open.spotify.com/album/'.FakePlatforms::SPOTIFY_ID, 'https://music.apple.com/album/6804428384', 'https://www.deezer.com/album/1061026552', 'https://tidal.com/album/'.FakePlatforms::TIDAL_ID, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://injust.bandcamp.com/track/white-men'], Json::at($graph, 2, 'sameAs'));
    }

    public function testTheFrenchPageIsTranslatedAndAnEpIsAnAlbum(): void
    {
        $client = self::createClient();
        self::release('Short', links: [new PlatformLink(Platform::Spotify, 'https://open.spotify.com/album/abc')], date: '2026-10-16', type: ReleaseType::Ep);

        $crawler = $client->request('GET', '/fr/listen/short');

        self::assertResponseIsSuccessful();
        self::assertSame('Injust · EP · 2026', $crawler->filter('.listen-meta')->text());
        self::assertSame('Écouter sur', $crawler->filter('#listen-on')->text());
        self::assertSame('Écouter', $crawler->filter('.listen-cta')->text());
        self::assertSame('/listen/short/spotify', $crawler->filter('.listen-links a')->attr('href'));
        self::assertSame('/fr', $crawler->filter('.listen-more')->attr('href'));
        self::assertSame('Plus d’Injust', $crawler->filter('.listen-more')->text());
        self::assertSame('Short', $crawler->filter('.listen-cover .cover-blank')->text());
        self::assertSame('music.album', $crawler->filter('meta[property="og:type"]')->attr('content'));
    }

    public function testAnUpcomingReleaseCountsDownAndAsksForAPreSave(): void
    {
        $client = self::createClient();
        self::release('We are fucked', date: '2026-12-04', type: ReleaseType::Album, smartLinkUrl: 'https://ffm.to/wearefucked');

        $crawler = $client->request('GET', '/listen/we-are-fucked');

        $countdown = $crawler->filter('.listen [data-island="Countdown"]');
        self::assertSame('2026-12-04T00:00:00+01:00', Json::string(Json::decode((string) $countdown->attr('data-props')), 'target'));
        self::assertSame('Out 04.12.2026', $countdown->text());
        $presave = $crawler->filter('a.listen-presave');
        self::assertSame(['Pre-save', 'https://ffm.to/wearefucked', 'noopener'], [$presave->text(), $presave->attr('href'), $presave->attr('rel')]);
        self::assertSame('Coming soon to every platform', $crawler->filter('.listen-soon')->text());
        self::assertCount(0, $crawler->filter('.listen-links'));
    }

    public function testAReleaseWithoutLinksIsComingSoonWithoutPreSave(): void
    {
        $client = self::createClient();
        self::release('Draft', upc: FakePlatforms::EVERYWHERE);

        $crawler = $client->request('GET', '/fr/listen/draft');

        self::assertSame('Bientôt sur toutes les plateformes', $crawler->filter('.listen-soon')->text());
        self::assertCount(0, $crawler->filter('.listen-links, .listen-presave, [data-island="Countdown"]'));
    }

    public function testTheListenPageDataHoldsTheReleaseAndItsOrderedLinks(): void
    {
        self::createClient();
        self::refresh(self::release(upc: FakePlatforms::PARTIAL, links: [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/x')]));

        $listen = self::getContainer()->get(MusicQueries::class)->listen('white-men-are-the-issue', new \DateTimeImmutable('2026-11-10'));

        self::assertSame('White Men are the Issue', $listen->release->title);
        self::assertSame(['apple-music', 'deezer', 'bandcamp'], array_map(static fn (PlatformLink $link): string => $link->platform->value, $listen->links));
    }

    public function testAnUnknownSlugIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/listen/unknown');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/fr/listen/unknown');
        self::assertResponseStatusCodeSame(404);

        $this->expectException(NotFound::class);
        self::getContainer()->get(MusicQueries::class)->listen('unknown', new \DateTimeImmutable());
    }
}
