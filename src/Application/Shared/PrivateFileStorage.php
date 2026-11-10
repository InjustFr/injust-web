<?php

declare(strict_types=1);

namespace App\Application\Shared;

interface PrivateFileStorage
{
    public function store(string $tmpPath, string $originalName): StoredFile;

    public function path(string $id): string;

    public function delete(string $id): void;
}
