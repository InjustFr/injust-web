<?php

declare(strict_types=1);

namespace App\Application\Beats;

use App\Domain\Shared\Price;

interface BeatsQueries
{
    public function page(): BeatsPageView;

    public function admin(): BeatsAdminView;

    public function beat(string $id): ?BeatView;

    /** @return list<BeatTierView> */
    public function tiersFor(string $beatId): array;

    public function fromPrice(string $beatId): ?Price;
}
