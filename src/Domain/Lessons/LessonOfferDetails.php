<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

use App\Domain\Lessons\Exception\InvalidDuration;
use App\Domain\Lessons\Exception\InvalidSessions;
use App\Domain\Lessons\Exception\UntitledOffer;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class LessonOfferDetails
{
    public const int MIN_MINUTES = 15;
    public const int MAX_MINUTES = 240;
    public const int MINUTES_STEP = 15;

    public function __construct(
        public LocalizedText $title,
        public LocalizedText $description,
        public OfferKind $kind,
        public int $sessions,
        public LessonFormat $format,
        public int $minutes,
        public Price $price,
    ) {
        if ($title->isEmpty()) {
            throw new UntitledOffer();
        }
        if (!$kind->allows($sessions)) {
            throw new InvalidSessions($kind->value);
        }
        if ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES || 0 !== $minutes % self::MINUTES_STEP) {
            throw new InvalidDuration(self::MIN_MINUTES, self::MAX_MINUTES, self::MINUTES_STEP);
        }
    }
}
