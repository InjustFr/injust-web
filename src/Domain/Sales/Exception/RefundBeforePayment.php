<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class RefundBeforePayment extends DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct('sales.refund_before_payment', ['reference' => $reference]);
    }
}
