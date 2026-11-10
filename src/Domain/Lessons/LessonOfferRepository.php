<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

use Symfony\Component\Uid\Ulid;

interface LessonOfferRepository
{
    public function add(LessonOffer $offer): void;

    public function get(Ulid $id): LessonOffer;

    public function find(Ulid $id): ?LessonOffer;

    public function remove(LessonOffer $offer): void;

    /** @return list<LessonOffer> */
    public function all(): array;
}
