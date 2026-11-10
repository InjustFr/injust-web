<?php

declare(strict_types=1);

namespace App\Domain\Beats\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidBpm extends DomainException
{
    public function __construct(int $min, int $max)
    {
        parent::__construct('beats.invalid_bpm', ['min' => $min, 'max' => $max]);
    }
}
