<?php

declare(strict_types=1);

namespace App\Domain\Music;

use Symfony\Component\Uid\Ulid;

interface VideoRepository
{
    public function add(Video $item): void;

    public function get(Ulid $id): Video;

    public function remove(Video $item): void;

    /** @return list<Video> */
    public function all(): array;
}
