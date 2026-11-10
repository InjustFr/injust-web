<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Positioned;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    public const int FRESH_DAYS = 120;
    public const int RECHECK_DAYS = 7;

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

    /** @var list<array{platform: string, url: string, source: string}> */
    #[ORM\Column(type: 'json')]
    private array $links = [];

    #[ORM\Column(length: Upc::MAX, unique: true, nullable: true)]
    private ?string $upc = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $linksCheckedAt = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $hiddenPlatforms = [];

    /** @var Collection<int, ReleaseLinkClick> */
    #[ORM\OneToMany(targetEntity: ReleaseLinkClick::class, mappedBy: 'release', fetch: 'EAGER')]
    #[ORM\OrderBy(['platform' => 'ASC'])]
    private Collection $clicks;

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
        $this->clicks = new ArrayCollection();
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
        $auto = $this->linksFrom(LinkSource::Auto);
        if ($details->upc !== $this->upc) {
            $this->upc = $details->upc;
            $this->linksCheckedAt = null;
            $auto = [];
        }
        $this->storeLinks(array_map(static fn (PlatformLink $link): PlatformLink => new PlatformLink($link->platform, $link->url), $details->links), $auto);
        $this->smartLinkUrl = $details->smartLinkUrl;
        $this->spotifyEmbed = $details->spotifyEmbed?->uri();
    }

    /** @param list<FoundLink> $found */
    public function applyFoundLinks(array $found, \DateTimeImmutable $now): void
    {
        $answered = array_map(static fn (FoundLink $link): Platform => $link->platform, $found);
        $auto = array_filter($this->linksFrom(LinkSource::Auto), static fn (PlatformLink $link): bool => !\in_array($link->platform, $answered, true));
        foreach ($found as $link) {
            if (null !== $link->url && !\in_array($link->platform, $this->hiddenPlatforms(), true)) {
                $auto[] = new PlatformLink($link->platform, $link->url, LinkSource::Auto);
            }
        }
        $this->storeLinks($this->linksFrom(LinkSource::Manual), $auto);
        $this->linksCheckedAt = $now;
    }

    /** @param list<Platform> $platforms */
    public function hidePlatforms(array $platforms): void
    {
        $this->hiddenPlatforms = array_values(array_unique(array_map(static fn (Platform $platform): string => $platform->value, $platforms)));
        $this->storeLinks($this->linksFrom(LinkSource::Manual), array_filter($this->linksFrom(LinkSource::Auto), static fn (PlatformLink $link): bool => !\in_array($link->platform, $platforms, true)));
    }

    /** @param list<Platform> $findable */
    public function linksDue(\DateTimeImmutable $now, array $findable): bool
    {
        if (null === $this->upc) {
            return false;
        }
        if (null === $this->linksCheckedAt || $this->releaseDate->format('Y-m-d') >= $now->setTimezone(new \DateTimeZone(self::TIMEZONE))->modify('-'.self::FRESH_DAYS.' days')->format('Y-m-d')) {
            return true;
        }
        $covered = [...array_map(static fn (PlatformLink $link): Platform => $link->platform, $this->links()), ...$this->hiddenPlatforms()];

        return [] !== array_filter($findable, static fn (Platform $platform): bool => !\in_array($platform, $covered, true))
            && $this->linksCheckedAt <= $now->modify('-'.self::RECHECK_DAYS.' days');
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
        return array_map(static fn (array $link): PlatformLink => new PlatformLink(Platform::from($link['platform']), $link['url'], LinkSource::from($link['source'])), $this->links);
    }

    /** @return array<int, PlatformLink> */
    private function linksFrom(LinkSource $source): array
    {
        return array_filter($this->links(), static fn (PlatformLink $link): bool => $link->source === $source);
    }

    /**
     * @param array<int, PlatformLink> $manual
     * @param array<int, PlatformLink> $auto
     */
    private function storeLinks(array $manual, array $auto): void
    {
        $taken = array_map(static fn (PlatformLink $link): Platform => $link->platform, $manual);
        $kept = array_filter($auto, static fn (PlatformLink $link): bool => !\in_array($link->platform, $taken, true));
        $this->links = array_map(static fn (PlatformLink $link): array => ['platform' => $link->platform->value, 'url' => $link->url, 'source' => $link->source->value], [...$manual, ...$kept]);
    }

    public function linkTo(Platform $platform): ?string
    {
        foreach ($this->links() as $link) {
            if ($link->platform === $platform) {
                return $link->url;
            }
        }

        return null;
    }

    public function upc(): ?string
    {
        return $this->upc;
    }

    public function linksCheckedAt(): ?\DateTimeImmutable
    {
        return $this->linksCheckedAt;
    }

    /** @return list<Platform> */
    public function hiddenPlatforms(): array
    {
        return array_map(Platform::from(...), $this->hiddenPlatforms);
    }

    /** @return array<string, int> */
    public function clicks(): array
    {
        $clicks = [];
        foreach ($this->clicks as $click) {
            $clicks[$click->platform()->value] = $click->clicks();
        }

        return $clicks;
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
