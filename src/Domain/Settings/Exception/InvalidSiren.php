<?php

declare(strict_types=1);

namespace App\Domain\Settings\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidSiren extends DomainException
{
    public function __construct(string $siren)
    {
        parent::__construct('settings.invalid_siren', ['siren' => $siren]);
    }
}
