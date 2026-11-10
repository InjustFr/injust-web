<?php

declare(strict_types=1);

namespace App\Domain\Beats;

use Symfony\Component\Uid\Ulid;

interface LicenceTierRepository
{
    public function add(LicenceTier $tier): void;

    public function get(Ulid $id): LicenceTier;

    public function remove(LicenceTier $tier): void;

    /** @return list<LicenceTier> */
    public function all(): array;

    public function exclusiveTaken(?Ulid $except): bool;
}
