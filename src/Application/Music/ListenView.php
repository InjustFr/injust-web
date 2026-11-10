<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;

final readonly class ListenView
{
    public const array ORDER = [
        Platform::Spotify,
        Platform::AppleMusic,
        Platform::YoutubeMusic,
        Platform::Deezer,
        Platform::Tidal,
        Platform::AmazonMusic,
        Platform::Youtube,
        Platform::Bandcamp,
        Platform::Soundcloud,
        Platform::Other,
    ];

    /** @param list<PlatformLink> $links */
    private function __construct(
        public ReleaseView $release,
        public array $links,
    ) {
    }

    public static function of(Release $release, \DateTimeImmutable $now): self
    {
        $links = [];
        foreach (self::ORDER as $platform) {
            $url = $release->linkTo($platform);
            if (null !== $url) {
                $links[] = new PlatformLink($platform, $url);
            }
        }

        return new self(ReleaseView::of($release, $now), $links);
    }
}
