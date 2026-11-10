<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\CreditNote;
use App\Domain\Sales\Customer;
use App\Domain\Sales\InvoiceLine;
use App\Domain\Sales\Seller;
use App\Domain\Shared\Price;

final readonly class CreditNoteView
{
    /** @param list<InvoiceLine> $lines */
    private function __construct(
        public string $id,
        public string $number,
        public string $invoiceNumber,
        public string $orderId,
        public string $orderReference,
        public string $locale,
        public \DateTimeImmutable $issuedAt,
        public Seller $seller,
        public Customer $buyer,
        public array $lines,
        public Price $amount,
        public string $vatMention,
        public ?string $reason,
        public string $stripeRefundId,
        public ?\DateTimeImmutable $mailedAt,
    ) {
    }

    public static function of(CreditNote $note): self
    {
        return new self(
            $note->id()->toBase32(),
            $note->number(),
            $note->invoiceNumber(),
            $note->orderId()->toBase32(),
            $note->orderReference(),
            $note->locale(),
            $note->issuedAt(),
            $note->seller(),
            $note->buyer(),
            $note->lines(),
            $note->amount(),
            $note->vatMention(),
            $note->reason(),
            $note->stripeRefundId(),
            $note->mailedAt(),
        );
    }
}
