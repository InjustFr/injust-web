<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesMails;

final class RecordingSalesMails implements SalesMails
{
    /** @var list<array{OrderView, InvoiceView}> */
    public array $sent = [];

    public int $failures = 0;

    public function __construct(private readonly Journal $journal = new Journal())
    {
    }

    public function confirmation(OrderView $order, InvoiceView $invoice): void
    {
        if ($this->failures > 0) {
            --$this->failures;
            $this->journal->write('mail failed');
            throw new \RuntimeException('SMTP is down.');
        }
        $this->journal->write('mail '.$order->reference.' '.$invoice->number);
        $this->sent[] = [$order, $invoice];
    }
}
