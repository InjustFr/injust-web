<?php

declare(strict_types=1);

namespace App\Domain\Production\Exception;

use App\Domain\Shared\Exception\DomainException;

final class MissingRoles extends DomainException
{
    public function __construct()
    {
        parent::__construct('production.missing_roles');
    }
}
