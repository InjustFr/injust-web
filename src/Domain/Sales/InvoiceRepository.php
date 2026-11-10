<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use Symfony\Component\Uid\Ulid;

interface InvoiceRepository
{
    public function add(Invoice $invoice): void;

    public function forOrder(Ulid $orderId): ?Invoice;
}
