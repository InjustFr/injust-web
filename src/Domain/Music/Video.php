<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Shared\Positioned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'music_video')]
class Video implements Positioned
{
    public const int MAX_TITLE = 200;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 11)]
    private string $youtubeId;

    #[ORM\Column(length: self::MAX_TITLE)]
    private string $title;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $thumbnail;

    #[ORM\Column]
    private int $position;

    private function __construct(YoutubeId $youtubeId, string $title, ?string $thumbnail, int $position)
    {
        $this->id = new Ulid();
        $this->youtubeId = $youtubeId->value;
        $this->title = trim($title);
        $this->thumbnail = $thumbnail;
        $this->position = $position;
    }

    public static function add(YoutubeId $youtubeId, string $title, ?string $thumbnail, int $position): self
    {
        return new self($youtubeId, $title, $thumbnail, $position);
    }

    public function rename(string $title): void
    {
        $this->title = trim($title);
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function youtubeId(): YoutubeId
    {
        return YoutubeId::parse($this->youtubeId);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function thumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function position(): int
    {
        return $this->position;
    }
}
