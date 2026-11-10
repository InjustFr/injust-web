<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Shared\HttpsUrl;
use App\Domain\Shared\Positioned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'music_social_link')]
class SocialLink implements Positioned
{
    public const int MAX_URL = 255;
    public const int MAX_LABEL = 60;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 20, enumType: SocialNetwork::class)]
    private SocialNetwork $network;

    #[ORM\Column(length: self::MAX_URL)]
    private string $url;

    #[ORM\Column(length: self::MAX_LABEL, nullable: true)]
    private ?string $label;

    #[ORM\Column]
    private int $position;

    private function __construct(SocialNetwork $network, string $url, ?string $label, int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
        $this->edit($network, $url, $label);
    }

    public static function add(SocialNetwork $network, string $url, ?string $label, int $position): self
    {
        return new self($network, $url, $label, $position);
    }

    public function edit(SocialNetwork $network, string $url, ?string $label): void
    {
        $label = trim($label ?? '');
        $this->network = $network;
        $this->url = HttpsUrl::of($url);
        $this->label = '' === $label ? null : $label;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function network(): SocialNetwork
    {
        return $this->network;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function position(): int
    {
        return $this->position;
    }
}
