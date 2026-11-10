<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Exception\DuplicatePlatform;
use App\Domain\Music\FoundLink;
use App\Domain\Music\LinkSource;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Shared\Exception\InvalidUrl;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleaseLinksTest extends TestCase
{
    private const string UPC = '701518909440';
    private const string SPOTIFY = 'https://open.spotify.com/album/auto';
    private const string DEEZER = 'https://www.deezer.com/album/1061026552';
    private const string APPLE = 'https://music.apple.com/album/6804428384';
    private const string TIDAL = 'https://tidal.com/album/1';
    private const string YT_MUSIC = 'https://music.youtube.com/playlist?list=OLAK5uy_nbWdzQYfxDeG7ADva9IU5qzaNv-W_ZloE';
    private const string YT = 'https://www.youtube.com/playlist?list=OLAK5uy_nbWdzQYfxDeG7ADva9IU5qzaNv-W_ZloE';

    /** @param list<PlatformLink> $links */
    private static function details(array $links = [], ?string $upc = self::UPC, string $date = '2026-06-01', string $title = 'White Men are the Issue'): ReleaseDetails
    {
        return new ReleaseDetails($title, null, ReleaseType::Single, new \DateTimeImmutable($date), new LocalizedText(), [], $links, null, null, $upc);
    }

    /** @param list<PlatformLink> $links */
    private static function release(array $links = [], ?string $upc = self::UPC, string $date = '2026-06-01'): Release
    {
        return Release::draft(self::details($links, $upc, $date), 0);
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-10 09:00:00 UTC');
    }

    /** @return list<array{string, string, string}> */
    private static function links(Release $release): array
    {
        return array_map(static fn (PlatformLink $link): array => [$link->platform->value, $link->url, $link->source->value], $release->links());
    }

    public function testANewReleaseKeepsItsUpcAndHasNeverBeenChecked(): void
    {
        $release = self::release();

        self::assertSame([self::UPC, null, [], []], [$release->upc(), $release->linksCheckedAt(), $release->hiddenPlatforms(), $release->clicks()]);
        self::assertNull(self::release(upc: ' ')->upc());
    }

    public function testLinksTypedInTheAdminAreManual(): void
    {
        $release = self::release([new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com', LinkSource::Auto)]);

        self::assertSame([['bandcamp', 'https://injust.bandcamp.com', 'manual']], self::links($release));
    }

    public function testFoundLinksAreAddedAsAutoAndTheCheckIsDated(): void
    {
        $release = self::release([new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com')]);

        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER), new FoundLink(Platform::AppleMusic, self::APPLE), new FoundLink(Platform::Tidal, null)], self::now());

        self::assertSame([
            ['bandcamp', 'https://injust.bandcamp.com', 'manual'],
            ['deezer', self::DEEZER, 'auto'],
            ['apple-music', self::APPLE, 'auto'],
        ], self::links($release));
        self::assertEquals(self::now(), $release->linksCheckedAt());
    }

    public function testAManualLinkWinsOverAFoundOne(): void
    {
        $release = self::release([new PlatformLink(Platform::Spotify, 'https://open.spotify.com/album/manual')]);

        $release->applyFoundLinks([new FoundLink(Platform::Spotify, self::SPOTIFY)], self::now());

        self::assertSame([['spotify', 'https://open.spotify.com/album/manual', 'manual']], self::links($release));
    }

    public function testARefreshReplacesTheAutoLinkOfEachPlatformThatAnswered(): void
    {
        $release = self::release();
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER), new FoundLink(Platform::Tidal, self::TIDAL), new FoundLink(Platform::Spotify, self::SPOTIFY)], self::now());

        $release->applyFoundLinks([new FoundLink(Platform::Deezer, 'https://www.deezer.com/album/2'), new FoundLink(Platform::Tidal, null)], self::now()->modify('+1 day'));

        self::assertSame([['spotify', self::SPOTIFY, 'auto'], ['deezer', 'https://www.deezer.com/album/2', 'auto']], self::links($release));
        self::assertEquals(self::now()->modify('+1 day'), $release->linksCheckedAt());
    }

    public function testAPlatformMissingFromTheAnswersKeepsItsPreviousAutoLink(): void
    {
        $release = self::release();
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER)], self::now());

        $release->applyFoundLinks([], self::now());

        self::assertSame([['deezer', self::DEEZER, 'auto']], self::links($release));
    }

    public function testNotFoundRemovesNothingManual(): void
    {
        $release = self::release([new PlatformLink(Platform::Deezer, 'https://www.deezer.com/album/manual')]);

        $release->applyFoundLinks([new FoundLink(Platform::Deezer, null)], self::now());

        self::assertSame([['deezer', 'https://www.deezer.com/album/manual', 'manual']], self::links($release));
    }

    public function testAHiddenPlatformLosesItsAutoLinkAndIsNeverAddedBack(): void
    {
        $release = self::release([new PlatformLink(Platform::Tidal, self::TIDAL)]);
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER), new FoundLink(Platform::AppleMusic, self::APPLE)], self::now());

        $release->hidePlatforms([Platform::Deezer, Platform::Deezer, Platform::Tidal]);
        self::assertSame([['tidal', self::TIDAL, 'manual'], ['apple-music', self::APPLE, 'auto']], self::links($release));
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER), new FoundLink(Platform::AppleMusic, self::APPLE)], self::now());

        self::assertSame([Platform::Deezer, Platform::Tidal], $release->hiddenPlatforms());
        self::assertSame([['tidal', self::TIDAL, 'manual'], ['apple-music', self::APPLE, 'auto']], self::links($release));
    }

    public function testAShownAgainPlatformComesBackAtTheNextRefresh(): void
    {
        $release = self::release();
        $release->hidePlatforms([Platform::Deezer]);

        $release->hidePlatforms([]);
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER)], self::now());

        self::assertSame([], $release->hiddenPlatforms());
        self::assertSame([['deezer', self::DEEZER, 'auto']], self::links($release));
    }

    public function testAnEditKeepsTheAutoLinksUnlessAManualOneTakesTheirPlatform(): void
    {
        $release = self::release();
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER), new FoundLink(Platform::Spotify, self::SPOTIFY)], self::now());

        $release->edit(self::details([new PlatformLink(Platform::Spotify, 'https://open.spotify.com/album/manual')]));

        self::assertSame([['spotify', 'https://open.spotify.com/album/manual', 'manual'], ['deezer', self::DEEZER, 'auto']], self::links($release));
        self::assertEquals(self::now(), $release->linksCheckedAt());
    }

    public function testANewUpcDropsTheAutoLinksAndAsksForANewCheck(): void
    {
        $release = self::release([new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com')]);
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER)], self::now());

        $release->edit(self::details([new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com')], '012345678905'));

        self::assertSame(['012345678905', null], [$release->upc(), $release->linksCheckedAt()]);
        self::assertSame([['bandcamp', 'https://injust.bandcamp.com', 'manual']], self::links($release));
    }

    public function testLinksFoundBySearchFollowTheAutoRules(): void
    {
        $release = self::release([new PlatformLink(Platform::Youtube, 'https://www.youtube.com/@injust')]);

        $release->applyFoundLinks([new FoundLink(Platform::YoutubeMusic, self::YT_MUSIC, LinkSource::Search), new FoundLink(Platform::Youtube, self::YT, LinkSource::Search)], self::now());
        self::assertSame([['youtube', 'https://www.youtube.com/@injust', 'manual'], ['youtube-music', self::YT_MUSIC, 'search']], self::links($release));

        $release->applyFoundLinks([], self::now());
        self::assertSame([['youtube', 'https://www.youtube.com/@injust', 'manual'], ['youtube-music', self::YT_MUSIC, 'search']], self::links($release));

        $release->applyFoundLinks([new FoundLink(Platform::YoutubeMusic, null, LinkSource::Search)], self::now());
        self::assertSame([['youtube', 'https://www.youtube.com/@injust', 'manual']], self::links($release));
    }

    public function testAHiddenPlatformLosesItsSearchLinkAndANewUpcDropsThem(): void
    {
        $release = self::release();
        $release->applyFoundLinks([new FoundLink(Platform::YoutubeMusic, self::YT_MUSIC, LinkSource::Search), new FoundLink(Platform::Youtube, self::YT, LinkSource::Search)], self::now());

        $release->hidePlatforms([Platform::Youtube]);
        $release->applyFoundLinks([new FoundLink(Platform::YoutubeMusic, self::YT_MUSIC, LinkSource::Search), new FoundLink(Platform::Youtube, self::YT, LinkSource::Search)], self::now());
        self::assertSame([['youtube-music', self::YT_MUSIC, 'search']], self::links($release));

        $release->edit(self::details());
        self::assertSame([['youtube-music', self::YT_MUSIC, 'search']], self::links($release));

        $release->edit(self::details(upc: '012345678905'));
        self::assertSame([], self::links($release));
    }

    public function testANewTitleDropsTheSearchLinksKeepsTheAutoOnesAndAsksForANewCheck(): void
    {
        $release = self::release();
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER), new FoundLink(Platform::YoutubeMusic, self::YT_MUSIC, LinkSource::Search)], self::now());

        $release->edit(self::details(title: 'White Men are the Issue (Remaster)'));

        self::assertSame([['deezer', self::DEEZER, 'auto']], self::links($release));
        self::assertNull($release->linksCheckedAt());
    }

    public function testTwoManualLinksOnOnePlatformAreRefused(): void
    {
        try {
            self::details([new PlatformLink(Platform::Other, 'https://injust.test/one'), new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com'), new PlatformLink(Platform::Other, 'https://injust.test/two')]);
            self::fail('A duplicate platform was accepted.');
        } catch (DuplicatePlatform $refused) {
            self::assertSame(['music.duplicate_platform', ['platform' => 'other']], [$refused->getMessage(), $refused->parameters()]);
        }
    }

    public function testEachPlatformLeadsToItsLink(): void
    {
        $release = self::release([new PlatformLink(Platform::Other, 'https://injust.test/one')]);
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER)], self::now());

        self::assertSame(['https://injust.test/one', self::DEEZER, null], [$release->linkTo(Platform::Other), $release->linkTo(Platform::Deezer), $release->linkTo(Platform::Spotify)]);
    }

    public function testAFoundLinkMustBeHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        new FoundLink(Platform::Deezer, 'http://www.deezer.com/album/1');
    }

    private const array FINDABLE = [Platform::Spotify, Platform::AppleMusic, Platform::Deezer, Platform::Tidal];

    /** @return iterable<string, array{bool, ?string, string, ?string}> */
    public static function dueCases(): iterable
    {
        yield 'never checked' => [true, self::UPC, '2020-01-01', null];
        yield 'no UPC' => [false, null, '2026-11-01', null];
        yield 'upcoming' => [true, self::UPC, '2026-12-01', '2026-11-10 08:00:00 UTC'];
        yield 'released 120 days ago' => [true, self::UPC, '2026-07-13', '2026-11-10 08:00:00 UTC'];
        yield 'released 121 days ago, missing platforms, checked 7 days ago' => [true, self::UPC, '2026-07-12', '2026-11-03 09:00:00 UTC'];
        yield 'released 121 days ago, missing platforms, checked 6 days ago' => [false, self::UPC, '2026-07-12', '2026-11-03 09:00:01 UTC'];
    }

    #[DataProvider('dueCases')]
    public function testALinkRefreshIsDueWhileTheReleaseIsFreshOrNeverChecked(bool $due, ?string $upc, string $date, ?string $checkedAt): void
    {
        $release = self::release(upc: $upc, date: $date);
        if (null !== $checkedAt) {
            $release->applyFoundLinks([], new \DateTimeImmutable($checkedAt));
        }

        self::assertSame($due, $release->linksDue(self::now(), self::FINDABLE));
    }

    public function testAnOldReleaseCoveredOnEveryFindablePlatformIsNotDue(): void
    {
        $release = self::release([new PlatformLink(Platform::Spotify, self::SPOTIFY)], date: '2025-01-01');
        $release->hidePlatforms([Platform::Tidal]);
        $release->applyFoundLinks([new FoundLink(Platform::Deezer, self::DEEZER), new FoundLink(Platform::AppleMusic, self::APPLE)], new \DateTimeImmutable('2026-10-01'));

        self::assertFalse($release->linksDue(self::now(), self::FINDABLE));
        self::assertTrue($release->linksDue(self::now(), [...self::FINDABLE, Platform::AmazonMusic]));
    }
}
