<?php

declare(strict_types=1);

namespace App\Domain\Beats\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidTags extends DomainException
{
    public function __construct(int $max, int $length)
    {
        parent::__construct('beats.invalid_tags', ['max' => $max, 'length' => $length]);
    }
}
