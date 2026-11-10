<?php

declare(strict_types=1);

namespace App\Domain\Beats\Exception;

use App\Domain\Shared\Exception\DomainException;

final class BeatSlugTaken extends DomainException
{
    public function __construct(string $slug)
    {
        parent::__construct('beats.slug_taken', ['slug' => $slug]);
    }
}
