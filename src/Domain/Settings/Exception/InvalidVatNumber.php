<?php

declare(strict_types=1);

namespace App\Domain\Settings\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidVatNumber extends DomainException
{
    public function __construct(string $vatNumber)
    {
        parent::__construct('settings.invalid_vat_number', ['vatNumber' => $vatNumber]);
    }
}
