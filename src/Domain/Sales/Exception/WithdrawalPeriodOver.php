<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class WithdrawalPeriodOver extends DomainException
{
    public function __construct(\DateTimeImmutable $endDate)
    {
        parent::__construct('sales.withdrawal_period_over', ['endDate' => $endDate]);
    }
}
