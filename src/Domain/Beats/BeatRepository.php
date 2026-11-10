<?php

declare(strict_types=1);

namespace App\Domain\Beats;

use Symfony\Component\Uid\Ulid;

interface BeatRepository
{
    public function add(Beat $beat): void;

    public function get(Ulid $id): Beat;

    public function find(Ulid $id): ?Beat;

    public function remove(Beat $beat): void;

    /** @return list<Beat> */
    public function all(): array;

    public function slugTaken(string $slug, ?Ulid $except): bool;
}
