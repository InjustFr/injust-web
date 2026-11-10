<?php

declare(strict_types=1);

namespace App\Domain\Production;

use App\Domain\Shared\Positioned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_item')]
class PortfolioItem implements Positioned
{
    public const int MAX_TITLE = 200;
    public const int MAX_ARTIST = 120;
    public const int MAX_URL = 255;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: self::MAX_TITLE)]
    private string $title;

    #[ORM\Column(length: self::MAX_ARTIST)]
    private string $artist;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles;

    #[ORM\Column(nullable: true)]
    private ?int $year;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $cover = null;

    #[ORM\Column(length: self::MAX_URL, nullable: true)]
    private ?string $url;

    #[ORM\Column]
    private int $position;

    private function __construct(PortfolioItemDetails $details, int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
        $this->edit($details);
    }

    public static function add(PortfolioItemDetails $details, int $position): self
    {
        return new self($details, $position);
    }

    public function edit(PortfolioItemDetails $details): void
    {
        $this->title = $details->title;
        $this->artist = $details->artist;
        $this->roles = array_map(static fn (ServiceKind $role): string => $role->value, $details->roles);
        $this->year = $details->year;
        $this->url = $details->url;
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

    public function id(): Ulid
    {
        return $this->id;
    }

    public function details(): PortfolioItemDetails
    {
        return new PortfolioItemDetails($this->title, $this->artist, array_map(ServiceKind::from(...), $this->roles), $this->year, $this->url);
    }

    public function cover(): ?string
    {
        return $this->cover;
    }

    public function position(): int
    {
        return $this->position;
    }
}
