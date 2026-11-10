<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class WithdrawalNotAllowed extends DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct('sales.withdrawal_not_allowed', ['reference' => $reference]);
    }
}
