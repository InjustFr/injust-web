<?php

declare(strict_types=1);

namespace App\Domain\Music\Exception;

use App\Domain\Shared\Exception\DomainException;

final class SlugTaken extends DomainException
{
    public function __construct(string $slug)
    {
        parent::__construct('music.slug_taken', ['slug' => $slug]);
    }
}
