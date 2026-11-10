<?php

declare(strict_types=1);

namespace App\Domain\Links;

use App\Domain\Links\Exception\InvalidLinkLabel;
use App\Domain\Music\SocialNetwork;
use App\Domain\Shared\HttpsUrl;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Positioned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'bio_link')]
class BioLink implements Positioned
{
    public const int MAX_URL = 255;
    public const int MAX_LABEL = 80;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'label_')]
    private LocalizedText $label;

    #[ORM\Column(length: self::MAX_URL)]
    private string $url;

    #[ORM\Column(length: 20, nullable: true, enumType: SocialNetwork::class)]
    private ?SocialNetwork $icon;

    #[ORM\Column]
    private bool $highlighted;

    #[ORM\Column]
    private bool $visible;

    #[ORM\Column]
    private int $clicks = 0;

    #[ORM\Column]
    private int $position;

    private function __construct(LocalizedText $label, string $url, ?SocialNetwork $icon, bool $highlighted, bool $visible, int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
        $this->edit($label, $url, $icon, $highlighted, $visible);
    }

    public static function add(LocalizedText $label, string $url, ?SocialNetwork $icon, bool $highlighted, bool $visible, int $position): self
    {
        return new self($label, $url, $icon, $highlighted, $visible, $position);
    }

    public function edit(LocalizedText $label, string $url, ?SocialNetwork $icon, bool $highlighted, bool $visible): void
    {
        if ($label->isEmpty() || max(mb_strlen($label->fr), mb_strlen($label->en)) > self::MAX_LABEL) {
            throw new InvalidLinkLabel(self::MAX_LABEL);
        }
        $this->url = HttpsUrl::of($url);
        $this->label = $label;
        $this->icon = $icon;
        $this->highlighted = $highlighted;
        $this->visible = $visible;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function label(): LocalizedText
    {
        return $this->label;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function icon(): ?SocialNetwork
    {
        return $this->icon;
    }

    public function highlighted(): bool
    {
        return $this->highlighted;
    }

    public function visible(): bool
    {
        return $this->visible;
    }

    public function clicks(): int
    {
        return $this->clicks;
    }

    public function position(): int
    {
        return $this->position;
    }
}
