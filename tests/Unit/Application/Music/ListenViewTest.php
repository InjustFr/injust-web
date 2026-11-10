<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Music;

use App\Application\Music\ListenView;
use App\Domain\Music\FoundLink;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\TestCase;

final class ListenViewTest extends TestCase
{
    public function testOneButtonPerPlatformInTheFixedOrder(): void
    {
        $release = self::release([
            new PlatformLink(Platform::Other, 'https://injust.test/one'),
            new PlatformLink(Platform::Soundcloud, 'https://soundcloud.com/injust/x'),
            new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/x'),
            new PlatformLink(Platform::Youtube, 'https://www.youtube.com/watch?v=x'),
            new PlatformLink(Platform::AmazonMusic, 'https://music.amazon.com/albums/x'),
            new PlatformLink(Platform::YoutubeMusic, 'https://music.youtube.com/playlist?list=x'),
        ]);
        $release->applyFoundLinks([new FoundLink(Platform::Tidal, 'https://tidal.com/album/1'), new FoundLink(Platform::Deezer, 'https://www.deezer.com/album/1'), new FoundLink(Platform::AppleMusic, 'https://music.apple.com/album/1'), new FoundLink(Platform::Spotify, 'https://open.spotify.com/album/1')], new \DateTimeImmutable('2026-11-10'));

        $view = ListenView::of($release, new \DateTimeImmutable('2026-11-10'));

        self::assertSame(
            ['spotify', 'apple-music', 'youtube-music', 'deezer', 'tidal', 'amazon-music', 'youtube', 'bandcamp', 'soundcloud', 'other'],
            array_map(static fn (PlatformLink $link): string => $link->platform->value, $view->links),
        );
        self::assertSame('https://injust.test/one', $view->links[9]->url);
        self::assertSame(['white-men-are-the-issue', false], [$view->release->slug, $view->release->upcoming]);
    }

    public function testAnUpcomingReleaseWithoutLinksHasNoButton(): void
    {
        $view = ListenView::of(self::release([], '2026-12-04'), new \DateTimeImmutable('2026-11-10'));

        self::assertSame([], $view->links);
        self::assertTrue($view->release->upcoming);
    }

    /** @param list<PlatformLink> $links */
    private static function release(array $links, string $date = '2026-06-01'): Release
    {
        return Release::draft(new ReleaseDetails('White Men are the Issue', null, ReleaseType::Single, new \DateTimeImmutable($date), new LocalizedText(), [], $links, null, null), 0);
    }
}
