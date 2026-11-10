<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface SalesMails
{
    public function confirmation(OrderView $order, InvoiceView $invoice): void;

    public function withdrawalAcknowledgement(OrderView $order, WithdrawalRequestView $withdrawal): void;

    public function withdrawalNotice(OrderView $order, WithdrawalRequestView $withdrawal): void;

    public function creditNote(OrderView $order, CreditNoteView $creditNote): void;

    public function exclusiveConflict(OrderView $order): void;
}
