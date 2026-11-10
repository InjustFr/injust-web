<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ProductUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('sales.product_unavailable');
    }
}
