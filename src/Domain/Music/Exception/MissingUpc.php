<?php

declare(strict_types=1);

namespace App\Domain\Music\Exception;

use App\Domain\Shared\Exception\DomainException;

final class MissingUpc extends DomainException
{
    public function __construct()
    {
        parent::__construct('music.missing_upc');
    }
}
