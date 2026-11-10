<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\OrderStatus;

interface SalesQueries
{
    /** @return list<OrderView> */
    public function orders(?OrderStatus $status = null, ?int $year = null): array;

    public function order(string $id): ?OrderView;

    public function invoice(string $orderId): ?InvoiceView;

    public function creditNote(string $id): ?CreditNoteView;

    /** @return list<RevenueLine> */
    public function revenueBook(int $year): array;

    public function totals(int $year): Totals;
}
