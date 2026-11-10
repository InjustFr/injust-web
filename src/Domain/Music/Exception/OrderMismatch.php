<?php

declare(strict_types=1);

namespace App\Domain\Music\Exception;

use App\Domain\Shared\Exception\DomainException;

final class OrderMismatch extends DomainException
{
    public function __construct()
    {
        parent::__construct('music.order_mismatch');
    }
}
