<?php

declare(strict_types=1);

namespace App\Domain\Beats\Exception;

use App\Domain\Shared\Exception\DomainException;

final class UnsupportedBeatFile extends DomainException
{
    public function __construct(string $format)
    {
        parent::__construct('beats.unsupported_file', ['format' => $format]);
    }
}
