<?php

declare(strict_types=1);

namespace App\Domain\Settings\Exception;

use App\Domain\Shared\Exception\DomainException;

final class EmptyLegalDocument extends DomainException
{
    public function __construct()
    {
        parent::__construct('legal.empty_document');
    }
}
