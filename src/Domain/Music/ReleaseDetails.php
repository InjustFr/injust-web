<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Music\Exception\DuplicatePlatform;
use App\Domain\Shared\HttpsUrl;
use App\Domain\Shared\LocalizedText;

final readonly class ReleaseDetails
{
    public string $title;
    public string $slug;
    public \DateTimeImmutable $releaseDate;
    public ?string $smartLinkUrl;
    public ?string $upc;

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
        ?string $upc = null,
    ) {
        $this->title = trim($title);
        $this->slug = Slug::of('' === trim($slug ?? '') ? $title : $slug);
        $this->releaseDate = $releaseDate->setTime(0, 0);
        $smartLinkUrl = HttpsUrl::orBlank($smartLinkUrl ?? '');
        $this->smartLinkUrl = '' === $smartLinkUrl ? null : $smartLinkUrl;
        $this->upc = Upc::orNull($upc);
        $platforms = [];
        foreach ($links as $link) {
            if (\in_array($link->platform, $platforms, true)) {
                throw new DuplicatePlatform($link->platform);
            }
            $platforms[] = $link->platform;
        }
    }
}
