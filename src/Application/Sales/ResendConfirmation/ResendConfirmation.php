<?php

declare(strict_types=1);

namespace App\Application\Sales\ResendConfirmation;

use Symfony\Component\Uid\Ulid;

final readonly class ResendConfirmation
{
    public function __construct(public Ulid $orderId)
    {
    }
}
