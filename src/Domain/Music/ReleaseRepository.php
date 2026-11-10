<?php

declare(strict_types=1);

namespace App\Domain\Music;

use Symfony\Component\Uid\Ulid;

interface ReleaseRepository
{
    public function add(Release $item): void;

    public function get(Ulid $id): Release;

    public function remove(Release $item): void;

    /** @return list<Release> */
    public function all(): array;

    public function slugTaken(string $slug, ?Ulid $except): bool;
}
