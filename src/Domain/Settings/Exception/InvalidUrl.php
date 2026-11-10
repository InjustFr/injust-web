<?php

declare(strict_types=1);

namespace App\Domain\Settings\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidUrl extends DomainException
{
    public function __construct(string $url)
    {
        parent::__construct('settings.invalid_url', ['url' => $url]);
    }
}
