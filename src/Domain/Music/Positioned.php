<?php

declare(strict_types=1);

namespace App\Domain\Music;

use Symfony\Component\Uid\Ulid;

interface Positioned
{
    public function id(): Ulid;

    public function moveTo(int $position): void;
}
