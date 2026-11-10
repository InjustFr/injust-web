<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Symfony\Component\Uid\Ulid;

interface Positioned
{
    public function id(): Ulid;

    public function position(): int;

    public function moveTo(int $position): void;
}
