<?php

declare(strict_types=1);

namespace App\Domain\Music\Exception;

use App\Domain\Music\Platform;
use App\Domain\Shared\Exception\DomainException;

final class DuplicatePlatform extends DomainException
{
    public function __construct(Platform $platform)
    {
        parent::__construct('music.duplicate_platform', ['platform' => $platform->value]);
    }
}
