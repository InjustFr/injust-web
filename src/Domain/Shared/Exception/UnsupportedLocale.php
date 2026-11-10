<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class UnsupportedLocale extends DomainException
{
    public function __construct(string $locale)
    {
        parent::__construct('shared.unsupported_locale', ['locale' => $locale]);
    }
}
