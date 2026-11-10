<?php

declare(strict_types=1);

namespace App\Domain\Music\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidHooks extends DomainException
{
    public function __construct(int $max, int $length)
    {
        parent::__construct('music.invalid_hooks', ['max' => $max, 'length' => $length]);
    }
}
