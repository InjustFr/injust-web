<?php

declare(strict_types=1);

namespace App\Application\Lessons\DeleteLessonOffer;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteLessonOffer
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
