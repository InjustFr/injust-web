<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Customer;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceLine;
use App\Domain\Sales\Seller;
use App\Domain\Shared\Price;

final readonly class InvoiceView
{
    /** @param list<InvoiceLine> $lines */
    private function __construct(
        public string $id,
        public string $number,
        public string $orderId,
        public string $orderReference,
        public string $locale,
        public \DateTimeImmutable $issuedAt,
        public Seller $seller,
        public Customer $buyer,
        public array $lines,
        public Price $total,
        public string $vatCase,
        public string $vatMention,
        public \DateTimeImmutable $paidAt,
        public string $paymentMethod,
        public ?string $creditedInvoiceNumber,
    ) {
    }

    public static function of(Invoice $invoice): self
    {
        return new self(
            $invoice->id()->toBase32(),
            $invoice->number(),
            $invoice->orderId()->toBase32(),
            $invoice->orderReference(),
            $invoice->locale(),
            $invoice->issuedAt(),
            $invoice->seller(),
            $invoice->buyer(),
            $invoice->lines(),
            $invoice->total(),
            $invoice->vatCase()->value,
            $invoice->vatMention(),
            $invoice->paidAt(),
            $invoice->paymentMethod(),
            $invoice->creditedInvoiceNumber(),
        );
    }
}
