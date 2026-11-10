<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class OrderNotPaid extends DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct('sales.order_not_paid', ['reference' => $reference]);
    }
}
