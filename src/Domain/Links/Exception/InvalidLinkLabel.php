<?php

declare(strict_types=1);

namespace App\Domain\Links\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidLinkLabel extends DomainException
{
    public function __construct(int $max)
    {
        parent::__construct('links.invalid_label', ['max' => $max]);
    }
}
