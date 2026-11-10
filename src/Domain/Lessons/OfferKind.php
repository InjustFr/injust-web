<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

enum OfferKind: string
{
    case Single = 'single';
    case Pack = 'pack';

    public function allows(int $sessions): bool
    {
        return self::Single === $this ? 1 === $sessions : $sessions >= 2;
    }
}
