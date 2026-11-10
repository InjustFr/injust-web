<?php

declare(strict_types=1);

namespace App\Domain\Production\Exception;

use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Shared\Exception\DomainException;

final class InvalidDeliveryDays extends DomainException
{
    public function __construct()
    {
        parent::__construct('production.invalid_delivery_days', ['min' => ProductionServiceDetails::MIN_DELIVERY_DAYS, 'max' => ProductionServiceDetails::MAX_DELIVERY_DAYS]);
    }
}
