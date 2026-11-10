<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\AddSocialLink\AddSocialLink;
use App\Application\Music\AddSocialLink\AddSocialLinkHandler;
use App\Application\Music\AddVideo\AddVideo;
use App\Application\Music\AddVideo\AddVideoHandler;
use App\Application\Music\EditMusicText\EditMusicText;
use App\Application\Music\EditMusicText\EditMusicTextHandler;
use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use App\Application\Music\ReleaseView;
use App\Application\Music\UploadPortrait\UploadPortrait;
use App\Application\Music\UploadPortrait\UploadPortraitHandler;
use App\Application\Music\UploadReleaseCover\UploadReleaseCover;
use App\Application\Music\UploadReleaseCover\UploadReleaseCoverHandler;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\SocialNetwork;
use App\Domain\Music\SpotifyEmbed;
use App\Domain\Music\Track;
use App\Domain\Music\YoutubeId;
use App\Domain\Settings\SiteImage;
use App\Domain\Shared\LocalizedText;
use App\Tests\Support\FakeYoutube;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Ulid;

final class HomePageTest extends WebTestCase
{
    use ConfiguresSite;

    private const string ULID_WEBP = '#^/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#';

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00'));
    }

    public function testAnUpcomingFeaturedReleaseCountsDownAndAsksForAPreSave(): void
    {
        $client = self::createClient();
        $album = self::release('We are fucked', '2026-12-04', ReleaseType::Album, smartLinkUrl: 'https://ffm.to/wearefucked', cover: true);
        self::feature($album);

        $crawler = $client->request('GET', '/');

        $hero = $crawler->filter('.hero-release');
        self::assertSame('We are fucked', $hero->filter('.hero-release-title')->text());
        self::assertSame('Album · 04.12.2026', $hero->filter('.hero-release-meta')->text());
        self::assertMatchesRegularExpression(self::ULID_WEBP, (string) $hero->filter('img')->attr('src'));
        $countdown = $hero->filter('[data-island="Countdown"]');
        self::assertSame(['target' => '2026-12-04T00:00:00+01:00', 'labels' => ['days' => 'Days', 'hours' => 'Hrs', 'minutes' => 'Min', 'seconds' => 'Sec', 'done' => 'Out now']], Json::decode((string) $countdown->attr('data-props')));
        self::assertSame('Out 04.12.2026', $countdown->text());
        $cta = $hero->filter('a.button');
        self::assertSame(['Pre-save', 'https://ffm.to/wearefucked'], [$cta->text(), $cta->attr('href')]);
        self::assertCount(0, $hero->filter('a[href="#videos"]'));
    }

    public function testAReleasedFeaturedReleaseIsToBeListenedToWithoutACountdown(): void
    {
        $client = self::createClient();
        self::feature(self::release('This is discrimination', '2026-11-06', smartLinkUrl: 'https://ffm.to/discrimination'));
        self::video('dQw4w9WgXcQ', 'This is discrimination (official video)');

        $crawler = $client->request('GET', '/fr');

        $hero = $crawler->filter('.hero-release');
        self::assertSame('Single · 06.11.2026', $hero->filter('.hero-release-meta')->text());
        self::assertCount(0, $hero->filter('[data-island="Countdown"]'));
        self::assertCount(0, $hero->filter('img'));
        self::assertSame(['Écouter', 'https://ffm.to/discrimination'], [$hero->filter('a.button')->text(), $hero->filter('a.button')->attr('href')]);
        self::assertSame('Voir le clip', $hero->filter('a[href="#videos"]')->text());
    }

    public function testAFeaturedReleaseWithoutASmartLinkHasNoCallToAction(): void
    {
        $client = self::createClient();
        self::feature(self::release('Draft', '2026-12-01'));

        $crawler = $client->request('GET', '/');

        self::assertCount(0, $crawler->filter('.hero-release a.button'));
        self::assertSame('Out 01.12.2026', $crawler->filter('.hero-release [data-island="Countdown"]')->text());
    }

    public function testWithoutAFeaturedReleaseTheHeroAnnouncesNewMusic(): void
    {
        $client = self::createClient();
        self::release('Single', '2026-09-25');

        $crawler = $client->request('GET', '/');

        self::assertSame('New music on the way', $crawler->filter('.hero-release')->text());
        self::assertSame('Injust: protest punk and hard rock.', $crawler->filter('.hero-tagline')->text());
    }

    public function testTheReleaseGridFollowsThePositionsAndOpensIntoTheTracklist(): void
    {
        $client = self::createClient();
        self::release('White Men are the Issue', '2026-09-25', cover: true);
        self::release(
            'We are fucked',
            '2026-12-04',
            ReleaseType::Album,
            tracks: [new Track(1, 'Intro', 65), new Track(2, 'Patriarchy', 605), new Track(3, 'Untimed', null)],
            links: [new PlatformLink(Platform::Spotify, 'https://open.spotify.com/album/abc'), new PlatformLink(Platform::AppleMusic, 'https://music.apple.com/x')],
            description: new LocalizedText('Le premier album.', 'The first album.'),
            spotify: 'https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy',
        );
        self::release('Short', '2026-10-16', ReleaseType::Ep);

        $crawler = $client->request('GET', '/');

        $releases = $crawler->filter('#releases details.release');
        self::assertSame(['release-white-men-are-the-issue', 'release-we-are-fucked', 'release-short'], $releases->each(static fn (Crawler $release): ?string => $release->attr('id')));
        self::assertSame(['White Men are the Issue', 'We are fucked', 'Short'], $releases->filter('summary .release-title')->each(static fn (Crawler $title): string => $title->text()));
        self::assertSame(['Single · 2026', 'Album · 2026', 'EP · 2026'], $releases->filter('summary .release-meta')->each(static fn (Crawler $meta): string => $meta->text()));
        self::assertMatchesRegularExpression(self::ULID_WEBP, (string) $releases->eq(0)->filter('summary img')->attr('src'));
        self::assertCount(0, $releases->eq(1)->filter('summary img'));
        $album = $releases->eq(1);
        self::assertSame(['Intro', 'Patriarchy', 'Untimed'], $album->filter('.tracklist .track-title')->each(static fn (Crawler $title): string => $title->text()));
        self::assertSame(['1:05', '10:05', ''], $album->filter('.tracklist .track-time')->each(static fn (Crawler $time): string => $time->text()));
        self::assertSame('The first album.', $album->filter('.release-description')->text());
        self::assertSame([['Spotify', 'https://open.spotify.com/album/abc'], ['Apple Music', 'https://music.apple.com/x']], $album->filter('.release-links a')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href')]));
        self::assertSame(['kind' => 'album', 'id' => '4aawyAB9vmqN3uQ7FjRGTy', 'title' => 'We are fucked', 'load' => 'Load the Spotify player', 'notice' => 'Loads a player from Spotify.'], Json::decode((string) $album->filter('[data-island="SpotifyPlayer"]')->attr('data-props')));
        self::assertSame('https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy', $album->filter('[data-island="SpotifyPlayer"] a')->attr('href'));
        self::assertCount(0, $releases->eq(0)->filter('.tracklist, .release-links, [data-island="SpotifyPlayer"], .release-description'));
    }

    public function testVideosAreFacadesWithTheSelfHostedThumbnail(): void
    {
        $client = self::createClient();
        self::video('dQw4w9WgXcQ', 'Official video');
        self::video(FakeYoutube::MISSING, 'Live');

        $crawler = $client->request('GET', '/');

        $videos = $crawler->filter('#videos [data-island="VideoFacade"]');
        self::assertCount(2, $videos);
        $first = Json::decode((string) $videos->eq(0)->attr('data-props'));
        self::assertSame(['youtubeId' => 'dQw4w9WgXcQ', 'title' => 'Official video', 'play' => 'Play “Official video”'], array_diff_key($first, ['thumbnail' => true]));
        self::assertMatchesRegularExpression(self::ULID_WEBP, Json::string($first, 'thumbnail'));
        self::assertSame(Json::string($first, 'thumbnail'), $videos->eq(0)->filter('img')->attr('src'));
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $videos->eq(0)->filter('a')->attr('href'));
        self::assertNull(Json::at(Json::decode((string) $videos->eq(1)->attr('data-props')), 'thumbnail'));
        self::assertCount(0, $videos->eq(1)->filter('img'));
        self::assertSame(['Official video', 'Live'], $crawler->filter('#videos .video-title')->each(static fn (Crawler $title): string => $title->text()));
    }

    public function testNoThirdPartyFrameOrScriptIsInThePage(): void
    {
        $client = self::createClient();
        self::feature(self::release('Album', '2026-12-04', spotify: 'spotify:album:4aawyAB9vmqN3uQ7FjRGTy', smartLinkUrl: 'https://ffm.to/x'));
        self::video('dQw4w9WgXcQ', 'Official video');
        self::upload(SiteImage::Hero);

        $crawler = $client->request('GET', '/');

        self::assertCount(0, $crawler->filter('iframe, object, embed, video, audio'));
        $sources = $crawler->filter('main img, link[rel="preload"]')->each(static fn (Crawler $node): string => (string) ($node->attr('src') ?? $node->attr('href')));
        self::assertCount(3, $sources);
        foreach ($sources as $source) {
            self::assertStringStartsWith('/media/images/', $source);
        }
    }

    public function testTheHooksTaglineManifestoAndBioAreLocalizedWithFallback(): void
    {
        $client = self::createClient();
        self::text(
            new LocalizedText('', 'Fuck the system'),
            new LocalizedText("Nous **refusons**.\n\n<script>alert(1)</script>", 'We **refuse**.'),
            new LocalizedText('', 'Born in a garage.'),
            ['Patriarchy needs to come down', 'No gods, no bosses'],
        );
        self::getContainer()->get(UploadPortraitHandler::class)(new UploadPortrait(self::fern()));

        $crawler = $client->request('GET', '/fr');

        self::assertSame('Fuck the system', $crawler->filter('.hero-tagline')->text());
        self::assertSame(['Patriarchy needs to come down', 'No gods, no bosses'], $crawler->filter('#hooks .hook')->each(static fn (Crawler $hook): string => $hook->text()));
        self::assertSame('en', $crawler->filter('#hooks')->attr('lang'));
        $manifesto = $crawler->filter('#manifesto .manifesto-body');
        self::assertSame('refusons', $manifesto->filter('strong')->text());
        self::assertCount(0, $manifesto->filter('script'));
        self::assertStringContainsString('&lt;script&gt;', $manifesto->html());
        self::assertSame('Born in a garage.', $crawler->filter('#manifesto .bio-body')->text());
        self::assertSame('Manifeste', $crawler->filter('#manifesto h2')->first()->text());
        self::assertMatchesRegularExpression(self::ULID_WEBP, (string) $crawler->filter('#manifesto img.portrait')->attr('src'));
    }

    public function testSocialLinksUseTheirLabelOrTheNetworkName(): void
    {
        $client = self::createClient();
        self::link(SocialNetwork::Instagram, 'https://instagram.com/injust', null);
        self::link(SocialNetwork::Other, 'https://injust.example/press', 'Press kit');

        $crawler = $client->request('GET', '/');

        self::assertSame([['Instagram', 'https://instagram.com/injust'], ['Press kit', 'https://injust.example/press']], $crawler->filter('#links a')->each(static fn (Crawler $link): array => [$link->filter('.link-name')->text(), $link->attr('href')]));
        self::assertSame(['me noopener', 'me noopener'], $crawler->filter('#links a')->each(static fn (Crawler $link): ?string => $link->attr('rel')));
    }

    public function testAnEmptyHomeHidesTheMusicSections(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#hooks, #releases, #videos, #manifesto, #links'));
        self::assertSame('New music on the way', $crawler->filter('.hero-release')->text());
    }

    public function testTheMusicIsDescribedForSearchEngines(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Hero);
        $album = self::release('We are fucked', '2026-12-04', ReleaseType::Album, tracks: [new Track(1, 'Intro', 65), new Track(2, 'Untimed', null)], links: [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/album/x')], cover: true);
        self::release('White Men are the Issue', '2026-09-25');
        self::feature($album);
        self::link(SocialNetwork::Instagram, 'https://instagram.com/injust', null);
        self::link(SocialNetwork::Youtube, 'https://youtube.com/@injust', null);

        $crawler = $client->request('GET', '/');

        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertSame(['https://instagram.com/injust', 'https://youtube.com/@injust'], Json::at($graph, 0, 'sameAs'));
        self::assertMatchesRegularExpression('#^http://localhost/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', Json::string($graph, 0, 'image'));
        self::assertSame([
            '@type' => 'MusicAlbum',
            '@id' => 'http://localhost/#release-we-are-fucked',
            'name' => 'We are fucked',
            'url' => 'http://localhost/#release-we-are-fucked',
            'albumReleaseType' => 'https://schema.org/AlbumRelease',
            'byArtist' => ['@id' => 'http://localhost/#band'],
            'datePublished' => '2026-12-04',
            'numTracks' => 2,
            'track' => [
                ['@type' => 'MusicRecording', 'name' => 'Intro', 'position' => 1, 'duration' => 'PT1M5S'],
                ['@type' => 'MusicRecording', 'name' => 'Untimed', 'position' => 2],
            ],
            'sameAs' => ['https://injust.bandcamp.com/album/x'],
            'image' => 'http://localhost'.$crawler->filter('.hero-release img')->attr('src'),
        ], Json::array($graph, 2));
        self::assertSame(['@type' => 'MusicAlbum', 'albumReleaseType' => 'https://schema.org/SingleRelease', 'numTracks' => 0], array_intersect_key(Json::array($graph, 3), ['@type' => 1, 'albumReleaseType' => 1, 'numTracks' => 1]));
        self::assertArrayNotHasKey('image', Json::array($graph, 3));
        self::assertSame('http://localhost'.$crawler->filter('.hero-release img')->attr('src'), $crawler->filter('meta[property="og:image"]')->attr('content'));
    }

    public function testWithoutMusicTheBandIsStillDescribedAndTheHeroIsTheSharingImage(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Hero);

        $crawler = $client->request('GET', '/');

        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertCount(2, $graph);
        self::assertArrayNotHasKey('sameAs', Json::array($graph, 0));
        self::assertSame('http://localhost'.$crawler->filter('.hero-photo')->attr('src'), Json::string($graph, 0, 'image'));
        self::assertSame(Json::string($graph, 0, 'image'), $crawler->filter('meta[property="og:image"]')->attr('content'));
    }

    /**
     * @param list<Track>        $tracks
     * @param list<PlatformLink> $links
     */
    private static function release(
        string $title,
        string $date,
        ReleaseType $type = ReleaseType::Single,
        array $tracks = [],
        array $links = [],
        ?string $smartLinkUrl = null,
        ?string $spotify = null,
        LocalizedText $description = new LocalizedText(),
        bool $cover = false,
    ): ReleaseView {
        $release = self::getContainer()->get(AddReleaseHandler::class)(new AddRelease(new ReleaseDetails(
            $title,
            null,
            $type,
            new \DateTimeImmutable($date),
            $description,
            $tracks,
            $links,
            $smartLinkUrl,
            null === $spotify ? null : SpotifyEmbed::parse($spotify),
        )));
        if ($cover) {
            self::getContainer()->get(UploadReleaseCoverHandler::class)(new UploadReleaseCover(Ulid::fromString($release->id), self::fern()));
        }

        return $release;
    }

    private static function feature(ReleaseView $release): void
    {
        self::getContainer()->get(FeatureReleaseHandler::class)(new FeatureRelease(Ulid::fromString($release->id), true));
    }

    private static function video(string $youtubeId, string $title): void
    {
        self::getContainer()->get(AddVideoHandler::class)(new AddVideo(YoutubeId::parse($youtubeId), $title));
    }

    /** @param list<string> $hooks */
    private static function text(LocalizedText $tagline, LocalizedText $manifesto, LocalizedText $bio, array $hooks): void
    {
        self::getContainer()->get(EditMusicTextHandler::class)(new EditMusicText($tagline, $manifesto, $bio, $hooks));
    }

    private static function link(SocialNetwork $network, string $url, ?string $label): void
    {
        self::getContainer()->get(AddSocialLinkHandler::class)(new AddSocialLink($network, $url, $label));
    }

    private static function fern(): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/large-fern.png';
    }
}
