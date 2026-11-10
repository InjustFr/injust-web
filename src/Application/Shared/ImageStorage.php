<?php

declare(strict_types=1);

namespace App\Application\Shared;

use Symfony\Component\Uid\Ulid;

interface ImageStorage
{
    public function store(Ulid $id, ResizedImage $image): string;

    public function delete(string $file): void;

    public function path(string $file): ?string;

    /** @return list<string> */
    public function files(): array;
}
