<?php

declare(strict_types=1);

namespace App\Domain\Contact\Exception;

use App\Domain\Shared\Exception\DomainException;

final class UnsupportedLocale extends DomainException
{
    public function __construct(string $locale)
    {
        parent::__construct('contact.unsupported_locale', ['locale' => $locale]);
    }
}
