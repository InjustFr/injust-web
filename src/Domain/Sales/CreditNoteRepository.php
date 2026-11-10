<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use Symfony\Component\Uid\Ulid;

interface CreditNoteRepository
{
    public function add(CreditNote $creditNote): void;

    public function find(Ulid $id): ?CreditNote;

    public function byRefund(string $stripeRefundId): ?CreditNote;

    /** @return list<CreditNote> */
    public function forOrder(Ulid $orderId): array;

    /** @return list<CreditNote> */
    public function issuedIn(int $year): array;
}
