<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidSessions extends DomainException
{
    public function __construct(string $kind)
    {
        parent::__construct('lessons.invalid_sessions', ['kind' => $kind]);
    }
}
