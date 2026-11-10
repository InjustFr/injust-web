<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'music_release')]
class Release implements Positioned
{
    public const int MAX_TITLE = 200;
    public const int MAX_URL = 255;
    public const string TIMEZONE = 'Europe/Paris';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: self::MAX_TITLE)]
    private string $title;

    #[ORM\Column(length: Slug::MAX, unique: true)]
    private string $slug;

    #[ORM\Column(length: 10, enumType: ReleaseType::class)]
    private ReleaseType $type;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $releaseDate;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $cover = null;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'description_')]
    private LocalizedText $description;

    /** @var list<array{position: int, title: string, durationSeconds: ?int}> */
    #[ORM\Column(type: 'json')]
    private array $tracks;

    /** @var list<array{platform: string, url: string}> */
    #[ORM\Column(type: 'json')]
    private array $links;

    #[ORM\Column(length: self::MAX_URL, nullable: true)]
    private ?string $smartLinkUrl;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $spotifyEmbed;

    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column]
    private int $position;

    private function __construct(ReleaseDetails $details, int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
        $this->edit($details);
    }

    public static function draft(ReleaseDetails $details, int $position): self
    {
        return new self($details, $position);
    }

    public function edit(ReleaseDetails $details): void
    {
        $this->title = $details->title;
        $this->slug = $details->slug;
        $this->type = $details->type;
        $this->releaseDate = $details->releaseDate;
        $this->description = $details->description;
        $this->tracks = array_map(static fn (Track $track): array => ['position' => $track->position, 'title' => $track->title, 'durationSeconds' => $track->durationSeconds], $details->tracks);
        $this->links = array_map(static fn (PlatformLink $link): array => ['platform' => $link->platform->value, 'url' => $link->url], $details->links);
        $this->smartLinkUrl = $details->smartLinkUrl;
        $this->spotifyEmbed = $details->spotifyEmbed?->uri();
    }

    public function feature(): void
    {
        $this->featured = true;
    }

    public function unfeature(): void
    {
        $this->featured = false;
    }

    public function useCover(string $image): ?string
    {
        $previous = $this->cover;
        $this->cover = $image;

        return $previous;
    }

    public function removeCover(): ?string
    {
        $previous = $this->cover;
        $this->cover = null;

        return $previous;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function isUpcoming(\DateTimeImmutable $now): bool
    {
        return $this->releaseAt() > $now;
    }

    public function releaseAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->releaseDate->format('Y-m-d'), new \DateTimeZone(self::TIMEZONE));
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function type(): ReleaseType
    {
        return $this->type;
    }

    public function releaseDate(): \DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function cover(): ?string
    {
        return $this->cover;
    }

    public function description(): LocalizedText
    {
        return $this->description;
    }

    /** @return list<Track> */
    public function tracks(): array
    {
        return array_map(static fn (array $track): Track => new Track($track['position'], $track['title'], $track['durationSeconds']), $this->tracks);
    }

    /** @return list<PlatformLink> */
    public function links(): array
    {
        return array_map(static fn (array $link): PlatformLink => new PlatformLink(Platform::from($link['platform']), $link['url']), $this->links);
    }

    public function smartLinkUrl(): ?string
    {
        return $this->smartLinkUrl;
    }

    public function spotifyEmbed(): ?SpotifyEmbed
    {
        return null === $this->spotifyEmbed ? null : SpotifyEmbed::parse($this->spotifyEmbed);
    }

    public function featured(): bool
    {
        return $this->featured;
    }

    public function position(): int
    {
        return $this->position;
    }
}
