<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceRepository;
use Symfony\Component\Uid\Ulid;

final class InMemoryInvoices implements InvoiceRepository
{
    /** @var list<Invoice> */
    public array $invoices = [];

    public function add(Invoice $invoice): void
    {
        $this->invoices[] = $invoice;
    }

    public function forOrder(Ulid $orderId): ?Invoice
    {
        return array_find($this->invoices, static fn (Invoice $invoice): bool => $invoice->orderId()->equals($orderId));
    }
}
