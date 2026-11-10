<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidRefundAmount extends DomainException
{
    public function __construct(int $remainingCents)
    {
        parent::__construct('sales.invalid_refund_amount', ['remaining' => $remainingCents / 100]);
    }
}
