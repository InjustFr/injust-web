<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class UnsupportedImage extends DomainException
{
    public function __construct()
    {
        parent::__construct('shared.unsupported_image');
    }
}
