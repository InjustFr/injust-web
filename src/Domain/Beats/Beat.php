<?php

declare(strict_types=1);

namespace App\Domain\Beats;

use App\Domain\Music\Slug;
use App\Domain\Shared\Positioned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'beat')]
class Beat implements Positioned
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: BeatDetails::MAX_TITLE)]
    private string $title;

    #[ORM\Column(length: Slug::MAX, unique: true)]
    private string $slug;

    #[ORM\Column]
    private int $bpm;

    #[ORM\Column(name: 'musical_key', length: 20, enumType: MusicalKey::class)]
    private MusicalKey $key;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $tags;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $cover = null;

    /** @var array{id: string, originalName: string, size: int, mimeType: string}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preview = null;

    /** @var array<string, array{id: string, originalName: string, size: int, mimeType: string}> */
    #[ORM\Column(type: 'json')]
    private array $files = [];

    #[ORM\Column]
    private bool $visible;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $soldExclusivelyAt = null;

    #[ORM\Column]
    private int $position;

    private function __construct(BeatDetails $details, int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
        $this->edit($details);
    }

    public static function add(BeatDetails $details, int $position): self
    {
        return new self($details, $position);
    }

    public function edit(BeatDetails $details): void
    {
        $this->title = $details->title;
        $this->slug = $details->slug;
        $this->bpm = $details->bpm;
        $this->key = $details->key;
        $this->tags = $details->tags;
        $this->visible = $details->visible;
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

    public function usePreview(BeatFile $preview): ?BeatFile
    {
        $previous = $this->preview();
        $this->preview = $preview->toArray();

        return $previous;
    }

    public function removePreview(): ?BeatFile
    {
        $previous = $this->preview();
        $this->preview = null;

        return $previous;
    }

    public function useFile(Deliverable $deliverable, BeatFile $file): ?BeatFile
    {
        $previous = $this->file($deliverable);
        $this->files[$deliverable->value] = $file->toArray();

        return $previous;
    }

    public function removeFile(Deliverable $deliverable): ?BeatFile
    {
        $previous = $this->file($deliverable);
        unset($this->files[$deliverable->value]);

        return $previous;
    }

    public function markSoldExclusively(\DateTimeImmutable $now): void
    {
        $this->soldExclusivelyAt ??= $now;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function hasFile(Deliverable $deliverable): bool
    {
        return isset($this->files[$deliverable->value]);
    }

    public function isAvailable(): bool
    {
        return $this->visible && null === $this->soldExclusivelyAt;
    }

    public function isListed(): bool
    {
        return $this->isAvailable() && null !== $this->preview;
    }

    public function purchasableAs(LicenceTier $tier): bool
    {
        return $this->isAvailable() && array_all($tier->details()->deliverables, $this->hasFile(...));
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function details(): BeatDetails
    {
        return new BeatDetails($this->title, $this->slug, $this->bpm, $this->key, $this->tags, $this->visible);
    }

    public function cover(): ?string
    {
        return $this->cover;
    }

    public function preview(): ?BeatFile
    {
        return null === $this->preview ? null : BeatFile::fromArray($this->preview);
    }

    public function file(Deliverable $deliverable): ?BeatFile
    {
        $file = $this->files[$deliverable->value] ?? null;

        return null === $file ? null : BeatFile::fromArray($file);
    }

    /** @return list<BeatFile> */
    public function files(): array
    {
        return array_values(array_filter(array_map($this->file(...), Deliverable::cases())));
    }

    public function soldExclusivelyAt(): ?\DateTimeImmutable
    {
        return $this->soldExclusivelyAt;
    }

    public function position(): int
    {
        return $this->position;
    }
}
