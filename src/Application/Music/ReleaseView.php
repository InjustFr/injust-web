<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;
use App\Domain\Music\SpotifyEmbed;
use App\Domain\Music\Track;
use App\Domain\Shared\LocalizedText;

final readonly class ReleaseView
{
    /**
     * @param list<Track>        $tracks
     * @param list<PlatformLink> $links
     */
    private function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $type,
        public string $releaseDate,
        public string $releaseAt,
        public bool $upcoming,
        public ?string $cover,
        public LocalizedText $description,
        public array $tracks,
        public array $links,
        public ?string $smartLinkUrl,
        public ?SpotifyEmbed $spotifyEmbed,
        public bool $featured,
        public int $position,
    ) {
    }

    public static function of(Release $release, \DateTimeImmutable $now): self
    {
        return new self(
            $release->id()->toBase32(),
            $release->title(),
            $release->slug(),
            $release->type()->value,
            $release->releaseDate()->format('Y-m-d'),
            $release->releaseAt()->format(\DATE_ATOM),
            $release->isUpcoming($now),
            $release->cover(),
            $release->description(),
            $release->tracks(),
            $release->links(),
            $release->smartLinkUrl(),
            $release->spotifyEmbed(),
            $release->featured(),
            $release->position(),
        );
    }
}
