<?php

declare(strict_types=1);

namespace App\Domain\Music\Exception;

use App\Domain\Shared\Exception\DomainException;

final class UpcTaken extends DomainException
{
    public function __construct(string $upc)
    {
        parent::__construct('music.upc_taken', ['upc' => $upc]);
    }
}
