<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class PaymentMismatch extends DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct('sales.payment_mismatch', ['reference' => $reference]);
    }
}
