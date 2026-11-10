<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Domain\Sales\CreditNote;
use App\Domain\Sales\CreditNoteRepository;
use Symfony\Component\Uid\Ulid;

final class InMemoryCreditNotes implements CreditNoteRepository
{
    /** @var list<CreditNote> */
    public array $creditNotes = [];

    public function add(CreditNote $creditNote): void
    {
        $this->creditNotes[] = $creditNote;
    }

    public function find(Ulid $id): ?CreditNote
    {
        return array_find($this->creditNotes, static fn (CreditNote $creditNote): bool => $creditNote->id()->equals($id));
    }

    public function byRefund(string $stripeRefundId): ?CreditNote
    {
        return array_find($this->creditNotes, static fn (CreditNote $creditNote): bool => $creditNote->stripeRefundId() === $stripeRefundId);
    }

    public function forOrder(Ulid $orderId): array
    {
        return array_values(array_filter($this->creditNotes, static fn (CreditNote $creditNote): bool => $creditNote->orderId()->equals($orderId)));
    }

    public function issuedIn(int $year): array
    {
        throw new \LogicException('Covered by the Doctrine repository tests.');
    }
}
