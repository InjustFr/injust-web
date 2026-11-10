<?php

declare(strict_types=1);

namespace App\Application\Sales\ResendWithdrawalAcknowledgement;

use Symfony\Component\Uid\Ulid;

final readonly class ResendWithdrawalAcknowledgement
{
    public function __construct(public Ulid $orderId)
    {
    }
}
