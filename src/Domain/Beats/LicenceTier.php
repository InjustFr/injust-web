<?php

declare(strict_types=1);

namespace App\Domain\Beats;

use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Positioned;
use App\Domain\Shared\Price;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'licence_tier')]
class LicenceTier implements Positioned
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'name_')]
    private LocalizedText $name;

    #[ORM\Embedded(class: Price::class, columnPrefix: 'price_')]
    private Price $price;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'summary_')]
    private LocalizedText $summary;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $deliverables;

    #[ORM\Column]
    private bool $exclusive;

    #[ORM\Column(nullable: true)]
    private ?int $streamCap;

    #[ORM\Column(nullable: true)]
    private ?int $copyCap;

    #[ORM\Column(nullable: true)]
    private ?int $videoCap;

    #[ORM\Column]
    private int $position;

    private function __construct(LicenceTierDetails $details, int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
        $this->edit($details);
    }

    public static function add(LicenceTierDetails $details, int $position): self
    {
        return new self($details, $position);
    }

    public function edit(LicenceTierDetails $details): void
    {
        $this->name = $details->name;
        $this->price = $details->price;
        $this->summary = $details->summary;
        $this->deliverables = array_map(static fn (Deliverable $deliverable): string => $deliverable->value, $details->deliverables);
        $this->exclusive = $details->exclusive;
        $this->streamCap = $details->streamCap;
        $this->copyCap = $details->copyCap;
        $this->videoCap = $details->videoCap;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function details(): LicenceTierDetails
    {
        return new LicenceTierDetails($this->name, $this->price, $this->summary, array_map(Deliverable::from(...), $this->deliverables), $this->exclusive, $this->streamCap, $this->copyCap, $this->videoCap);
    }

    public function position(): int
    {
        return $this->position;
    }
}
