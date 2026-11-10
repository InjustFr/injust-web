<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class InvalidFocus extends DomainException
{
    public function __construct(int $step)
    {
        parent::__construct('shared.invalid_focus', ['step' => $step]);
    }
}
