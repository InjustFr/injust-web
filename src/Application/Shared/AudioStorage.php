<?php

declare(strict_types=1);

namespace App\Application\Shared;

interface AudioStorage
{
    public function store(string $tmpPath): string;

    public function path(string $id): string;

    public function delete(string $id): void;
}
