<?php

declare(strict_types=1);

namespace App\Domain\Beats\Exception;

use App\Domain\Shared\Exception\DomainException;

final class BeatFileTooLarge extends DomainException
{
    public function __construct(int $megabytes)
    {
        parent::__construct('beats.file_too_large', ['megabytes' => $megabytes]);
    }
}
