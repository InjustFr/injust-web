<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Shared\HttpsUrl;
use App\Domain\Shared\LocalizedText;

final readonly class ReleaseDetails
{
    public string $title;
    public string $slug;
    public \DateTimeImmutable $releaseDate;
    public ?string $smartLinkUrl;

    /**
     * @param list<Track>        $tracks
     * @param list<PlatformLink> $links
     */
    public function __construct(
        string $title,
        ?string $slug,
        public ReleaseType $type,
        \DateTimeImmutable $releaseDate,
        public LocalizedText $description,
        public array $tracks,
        public array $links,
        ?string $smartLinkUrl,
        public ?SpotifyEmbed $spotifyEmbed,
    ) {
        $this->title = trim($title);
        $this->slug = Slug::of('' === trim($slug ?? '') ? $title : $slug);
        $this->releaseDate = $releaseDate->setTime(0, 0);
        $smartLinkUrl = HttpsUrl::orBlank($smartLinkUrl ?? '');
        $this->smartLinkUrl = '' === $smartLinkUrl ? null : $smartLinkUrl;
    }
}
