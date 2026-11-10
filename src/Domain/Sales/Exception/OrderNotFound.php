<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class OrderNotFound extends DomainException
{
    public function __construct(string $by, string $id)
    {
        parent::__construct('sales.order_not_found', ['by' => $by, 'id' => $id]);
    }
}
