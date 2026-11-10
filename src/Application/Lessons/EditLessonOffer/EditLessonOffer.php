<?php

declare(strict_types=1);

namespace App\Application\Lessons\EditLessonOffer;

use App\Domain\Lessons\LessonOfferDetails;
use Symfony\Component\Uid\Ulid;

final readonly class EditLessonOffer
{
    public function __construct(
        public Ulid $id,
        public LessonOfferDetails $details,
    ) {
    }
}
