<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface SalesMails
{
    public function confirmation(OrderView $order, InvoiceView $invoice): void;
}
