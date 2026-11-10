<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use Symfony\Component\Uid\Ulid;

interface WithdrawalRequestRepository
{
    public function add(WithdrawalRequest $request): void;

    public function forOrder(Ulid $orderId): ?WithdrawalRequest;
}
