<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\SpotifyEmbed;
use App\Domain\Music\Track;
use App\Domain\Shared\Exception\InvalidUrl;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\TestCase;

final class ReleaseTest extends TestCase
{
    public static function details(string $title = 'No Gods', ?string $slug = null, string $date = '2026-12-01', ?string $smartLinkUrl = null, ?SpotifyEmbed $spotify = null): ReleaseDetails
    {
        return new ReleaseDetails(
            $title,
            $slug,
            ReleaseType::Album,
            new \DateTimeImmutable($date),
            new LocalizedText('Premier album', 'First album'),
            [new Track(1, 'Intro', 61), new Track(2, 'Riot', null)],
            [new PlatformLink(Platform::Spotify, 'https://open.spotify.com/album/x'), new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com')],
            $smartLinkUrl,
            $spotify,
        );
    }

    public function testADraftKeepsItsDetailsAndIsNotFeatured(): void
    {
        $spotify = SpotifyEmbed::parse('spotify:album:4aawyAB9vmqN3uQ7FjRGTy');
        $release = Release::draft(self::details(smartLinkUrl: ' https://ffm.to/nogods ', spotify: $spotify), 4);

        self::assertSame(['No Gods', 'no-gods', ReleaseType::Album, '2026-12-01', 4, false, null], [$release->title(), $release->slug(), $release->type(), $release->releaseDate()->format('Y-m-d'), $release->position(), $release->featured(), $release->cover()]);
        self::assertSame('First album', $release->description()->in('en'));
        self::assertEquals([new Track(1, 'Intro', 61), new Track(2, 'Riot', null)], $release->tracks());
        self::assertEquals([new PlatformLink(Platform::Spotify, 'https://open.spotify.com/album/x'), new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com')], $release->links());
        self::assertSame('https://ffm.to/nogods', $release->smartLinkUrl());
        self::assertEquals($spotify, $release->spotifyEmbed());
    }

    public function testTheTitleIsTrimmedAndTheSlugCanBeChosen(): void
    {
        $release = Release::draft(self::details(title: '  No Gods  ', slug: 'Custom Slug'), 0);
        $release->edit(new ReleaseDetails('  No Gods  ', 'Custom Slug', ReleaseType::Ep, new \DateTimeImmutable('2026-01-01'), new LocalizedText(), [new Track(3, 'Riot', null)], [], null, null));

        self::assertSame(['No Gods', 'custom-slug'], [$release->title(), $release->slug()]);
        self::assertEquals([new Track(3, 'Riot', null)], $release->tracks());
    }

    public function testABlankSlugFallsBackToTheTitle(): void
    {
        self::assertSame('no-gods', self::details(slug: '  ')->slug);
    }

    public function testAnEditReplacesEveryDetail(): void
    {
        $release = Release::draft(self::details(smartLinkUrl: 'https://ffm.to/nogods', spotify: SpotifyEmbed::parse('spotify:album:4aawyAB9vmqN3uQ7FjRGTy')), 0);

        $release->edit(new ReleaseDetails('Riot', null, ReleaseType::Single, new \DateTimeImmutable('2025-01-02'), new LocalizedText(), [], [], ' ', null));

        self::assertSame(['Riot', 'riot', ReleaseType::Single, '2025-01-02', [], [], null, null], [$release->title(), $release->slug(), $release->type(), $release->releaseDate()->format('Y-m-d'), $release->tracks(), $release->links(), $release->smartLinkUrl(), $release->spotifyEmbed()]);
        self::assertTrue($release->description()->isEmpty());
    }

    public function testTheSmartLinkMustBeHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        self::details(smartLinkUrl: 'http://ffm.to/nogods');
    }

    public function testAReleaseIsUpcomingUntilItsDay(): void
    {
        $release = Release::draft(self::details(date: '2026-12-01'), 0);

        self::assertTrue($release->isUpcoming(new \DateTimeImmutable('2026-11-30 22:59:59 UTC')));
        self::assertFalse($release->isUpcoming(new \DateTimeImmutable('2026-11-30 23:00:00 UTC')));
        self::assertFalse($release->isUpcoming(new \DateTimeImmutable('2026-11-30 23:30:00 UTC')));
        self::assertFalse($release->isUpcoming(new \DateTimeImmutable('2026-12-02 UTC')));
    }

    public function testAReleaseComesOutAtMidnightInParis(): void
    {
        self::assertSame('2026-12-01T00:00:00+01:00', Release::draft(self::details(date: '2026-12-01'), 0)->releaseAt()->format(\DATE_ATOM));
        self::assertSame('2026-07-01T00:00:00+02:00', Release::draft(self::details(date: '2026-07-01'), 0)->releaseAt()->format(\DATE_ATOM));
        self::assertSame('2026-12-01T00:00:00+01:00', Release::draft(self::details(date: '2026-12-01T23:30:00-08:00'), 0)->releaseAt()->format(\DATE_ATOM));
    }

    public function testTheReleaseDayIsTheCalendarDayGiven(): void
    {
        $release = Release::draft(self::details(date: '2026-12-01T23:30:00+05:00'), 0);

        self::assertSame('2026-12-01 00:00:00', $release->releaseDate()->format('Y-m-d H:i:s'));
    }

    public function testItIsFeaturedAndUnfeatured(): void
    {
        $release = Release::draft(self::details(), 0);

        $release->feature();
        self::assertTrue($release->featured());
        $release->unfeature();
        self::assertFalse($release->featured());
    }

    public function testTheCoverReturnsTheOneItReplacesAndItMoves(): void
    {
        $release = Release::draft(self::details(), 0);

        self::assertNull($release->useCover('C1'));
        self::assertSame('C1', $release->useCover('C2'));
        self::assertSame('C2', $release->removeCover());
        self::assertNull($release->cover());
        $release->moveTo(7);
        self::assertSame(7, $release->position());
    }

    public function testATrackKeepsItsPositionTitleAndDuration(): void
    {
        $track = new Track(3, ' Riot ', 185);

        self::assertSame([3, 'Riot', 185], [$track->position, $track->title, $track->durationSeconds]);
    }

    public function testAPlatformLinkMustBeHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        new PlatformLink(Platform::Deezer, 'deezer.com/album/1');
    }
}
