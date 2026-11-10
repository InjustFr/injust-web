<?php

declare(strict_types=1);

namespace App\Application\Lessons;

use App\Domain\Lessons\LessonOffer;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class LessonOfferView
{
    private function __construct(
        public string $id,
        public LocalizedText $title,
        public LocalizedText $description,
        public string $kind,
        public int $sessions,
        public string $format,
        public int $minutes,
        public Price $price,
        public int $position,
    ) {
    }

    public static function of(LessonOffer $offer): self
    {
        $details = $offer->details();

        return new self($offer->id()->toBase32(), $details->title, $details->description, $details->kind->value, $details->sessions, $details->format->value, $details->minutes, $details->price, $offer->position());
    }
}
