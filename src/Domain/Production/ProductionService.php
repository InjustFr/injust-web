<?php

declare(strict_types=1);

namespace App\Domain\Production;

use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Positioned;
use App\Domain\Shared\Price;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'production_service')]
class ProductionService implements Positioned
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 20, enumType: ServiceKind::class)]
    private ServiceKind $kind;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'title_')]
    private LocalizedText $title;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'description_')]
    private LocalizedText $description;

    #[ORM\Column(length: 10, enumType: Pricing::class)]
    private Pricing $pricing;

    #[ORM\Embedded(class: Price::class, columnPrefix: 'price_')]
    private Price $price;

    #[ORM\Column(nullable: true)]
    private ?int $deliveryDays;

    #[ORM\Column(nullable: true)]
    private ?int $revisions;

    #[ORM\Column]
    private int $position;

    private function __construct(ProductionServiceDetails $details, int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
        $this->edit($details);
    }

    public static function add(ProductionServiceDetails $details, int $position): self
    {
        return new self($details, $position);
    }

    public function edit(ProductionServiceDetails $details): void
    {
        $this->kind = $details->kind;
        $this->title = $details->title;
        $this->description = $details->description;
        $this->pricing = $details->pricing;
        $this->price = $details->price;
        $this->deliveryDays = $details->deliveryDays;
        $this->revisions = $details->revisions;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function details(): ProductionServiceDetails
    {
        return new ProductionServiceDetails($this->title, $this->description, $this->kind, $this->pricing, $this->price, $this->deliveryDays, $this->revisions);
    }

    public function position(): int
    {
        return $this->position;
    }
}
