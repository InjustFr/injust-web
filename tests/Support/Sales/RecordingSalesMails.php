<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Sales\CreditNoteView;
use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesMails;
use App\Application\Sales\WithdrawalRequestView;

final class RecordingSalesMails implements SalesMails
{
    /** @var list<array{OrderView, InvoiceView}> */
    public array $sent = [];

    /** @var list<array{OrderView, WithdrawalRequestView}> */
    public array $acknowledgements = [];

    /** @var list<array{OrderView, WithdrawalRequestView}> */
    public array $notices = [];

    /** @var list<array{OrderView, CreditNoteView}> */
    public array $creditNotes = [];

    /** @var list<OrderView> */
    public array $conflicts = [];

    public int $failures = 0;

    public ?string $broken = null;

    public function __construct(private readonly Journal $journal = new Journal())
    {
    }

    public function confirmation(OrderView $order, InvoiceView $invoice): void
    {
        $this->send('mail '.$order->reference.' '.$invoice->number);
        $this->sent[] = [$order, $invoice];
    }

    public function withdrawalAcknowledgement(OrderView $order, WithdrawalRequestView $withdrawal): void
    {
        $this->send('mail acknowledgement '.$order->reference);
        $this->acknowledgements[] = [$order, $withdrawal];
    }

    public function withdrawalNotice(OrderView $order, WithdrawalRequestView $withdrawal): void
    {
        $this->send('mail notice '.$order->reference);
        $this->notices[] = [$order, $withdrawal];
    }

    public function creditNote(OrderView $order, CreditNoteView $creditNote): void
    {
        $this->send('mail credit note '.$creditNote->number);
        $this->creditNotes[] = [$order, $creditNote];
    }

    public function exclusiveConflict(OrderView $order): void
    {
        $this->send('mail conflict '.$order->reference);
        $this->conflicts[] = $order;
    }

    private function send(string $entry): void
    {
        if (null !== $this->broken && str_contains($entry, $this->broken)) {
            $this->journal->write('mail failed');
            throw new \RuntimeException('SMTP is down.');
        }
        if ($this->failures > 0) {
            --$this->failures;
            $this->journal->write('mail failed');
            throw new \RuntimeException('SMTP is down.');
        }
        $this->journal->write($entry);
    }
}
