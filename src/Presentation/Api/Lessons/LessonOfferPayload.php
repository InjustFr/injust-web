<?php

declare(strict_types=1);

namespace App\Presentation\Api\Lessons;

use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class LessonOfferPayload
{
    public function __construct(
        public OfferKind $kind,
        public int $sessions,
        public LessonFormat $format,
        public int $minutes,
        public Price $price,
        public LocalizedText $title = new LocalizedText(),
        public LocalizedText $description = new LocalizedText(),
    ) {
    }

    public function details(): LessonOfferDetails
    {
        return new LessonOfferDetails($this->title, $this->description, $this->kind, $this->sessions, $this->format, $this->minutes, $this->price);
    }
}
