<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Domain\Sales\WithdrawalRequest;
use App\Domain\Sales\WithdrawalRequestRepository;
use Symfony\Component\Uid\Ulid;

final class InMemoryWithdrawals implements WithdrawalRequestRepository
{
    /** @var list<WithdrawalRequest> */
    public array $requests = [];

    public function __construct(private readonly Journal $journal = new Journal())
    {
    }

    public function add(WithdrawalRequest $request): void
    {
        $this->journal->write('store withdrawal '.$request->orderReference());
        $this->requests[] = $request;
    }

    public function forOrder(Ulid $orderId): ?WithdrawalRequest
    {
        return array_find($this->requests, static fn (WithdrawalRequest $request): bool => $request->orderId()->equals($orderId));
    }
}
