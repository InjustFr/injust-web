<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exception;

use App\Domain\Shared\Exception\DomainException;

final class AccessDenied extends DomainException
{
    public function __construct()
    {
        parent::__construct('identity.access_denied');
    }
}
