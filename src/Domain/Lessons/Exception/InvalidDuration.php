<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidDuration extends DomainException
{
    public function __construct(int $min, int $max, int $step)
    {
        parent::__construct('lessons.invalid_duration', ['min' => $min, 'max' => $max, 'step' => $step]);
    }
}
