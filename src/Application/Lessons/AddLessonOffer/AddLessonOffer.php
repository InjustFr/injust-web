<?php

declare(strict_types=1);

namespace App\Application\Lessons\AddLessonOffer;

use App\Domain\Lessons\LessonOfferDetails;

final readonly class AddLessonOffer
{
    public function __construct(
        public LessonOfferDetails $details,
    ) {
    }
}
