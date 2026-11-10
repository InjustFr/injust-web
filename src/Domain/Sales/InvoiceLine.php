<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Shared\Price;

/**
 * @phpstan-type InvoiceLineData array{label: string, quantity: int, unitPriceCents: int, totalCents: int}
 */
final readonly class InvoiceLine
{
    public function __construct(
        public string $label,
        public int $quantity,
        public Price $unitPrice,
        public Price $total,
    ) {
    }

    /** @param InvoiceLineData $data */
    public static function fromArray(array $data): self
    {
        return new self($data['label'], $data['quantity'], new Price($data['unitPriceCents']), new Price($data['totalCents']));
    }

    /** @return InvoiceLineData */
    public function toArray(): array
    {
        return ['label' => $this->label, 'quantity' => $this->quantity, 'unitPriceCents' => $this->unitPrice->cents, 'totalCents' => $this->total->cents];
    }
}
