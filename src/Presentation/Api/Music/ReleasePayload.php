<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Domain\Music\LinkSource;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\Slug;
use App\Domain\Music\SpotifyEmbed;
use App\Domain\Music\Track;
use App\Domain\Shared\LocalizedText;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReleasePayload
{
    /**
     * @param list<TrackPayload>        $tracks
     * @param list<PlatformLinkPayload> $links
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Release::MAX_TITLE)]
        public string $title = '',
        #[Assert\Length(max: Slug::MAX)]
        public ?string $slug = null,
        public ReleaseType $type = ReleaseType::Single,
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $releaseDate = '',
        public LocalizedText $description = new LocalizedText(),
        #[Assert\Valid]
        public array $tracks = [],
        public array $links = [],
        #[Assert\Length(max: Release::MAX_URL)]
        public ?string $smartLinkUrl = null,
        public ?string $spotifyUrl = null,
        #[Assert\Length(max: 20)]
        public ?string $upc = null,
    ) {
    }

    public function details(): ReleaseDetails
    {
        $spotifyUrl = trim($this->spotifyUrl ?? '');

        return new ReleaseDetails(
            $this->title,
            $this->slug,
            $this->type,
            new \DateTimeImmutable($this->releaseDate),
            $this->description,
            array_map(static fn (TrackPayload $track, int $index): Track => new Track($index + 1, $track->title, $track->durationSeconds), $this->tracks, array_keys($this->tracks)),
            array_values(array_map(static fn (PlatformLinkPayload $link): PlatformLink => new PlatformLink($link->platform, $link->url), array_filter($this->links, static fn (PlatformLinkPayload $link): bool => LinkSource::Manual === $link->source))),
            $this->smartLinkUrl,
            '' === $spotifyUrl ? null : SpotifyEmbed::parse($spotifyUrl),
            $this->upc,
        );
    }
}
