<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Positioned;
use App\Domain\Shared\Price;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'lesson_offer')]
class LessonOffer implements Positioned
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'title_')]
    private LocalizedText $title;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'description_')]
    private LocalizedText $description;

    #[ORM\Column(length: 10, enumType: OfferKind::class)]
    private OfferKind $kind;

    #[ORM\Column]
    private int $sessions;

    #[ORM\Column(length: 20, enumType: LessonFormat::class)]
    private LessonFormat $format;

    #[ORM\Column]
    private int $minutes;

    #[ORM\Embedded(class: Price::class, columnPrefix: 'price_')]
    private Price $price;

    #[ORM\Column]
    private int $position;

    private function __construct(LessonOfferDetails $details, int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
        $this->edit($details);
    }

    public static function add(LessonOfferDetails $details, int $position): self
    {
        return new self($details, $position);
    }

    public function edit(LessonOfferDetails $details): void
    {
        $this->title = $details->title;
        $this->description = $details->description;
        $this->kind = $details->kind;
        $this->sessions = $details->sessions;
        $this->format = $details->format;
        $this->minutes = $details->minutes;
        $this->price = $details->price;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function details(): LessonOfferDetails
    {
        return new LessonOfferDetails($this->title, $this->description, $this->kind, $this->sessions, $this->format, $this->minutes, $this->price);
    }

    public function position(): int
    {
        return $this->position;
    }
}
