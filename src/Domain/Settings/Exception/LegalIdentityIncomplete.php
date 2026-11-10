<?php

declare(strict_types=1);

namespace App\Domain\Settings\Exception;

use App\Domain\Shared\Exception\DomainException;

final class LegalIdentityIncomplete extends DomainException
{
    public function __construct()
    {
        parent::__construct('settings.legal_identity_incomplete');
    }
}
