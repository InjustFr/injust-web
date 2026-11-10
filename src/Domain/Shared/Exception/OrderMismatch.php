<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class OrderMismatch extends DomainException
{
    public function __construct()
    {
        parent::__construct('shared.order_mismatch');
    }
}
