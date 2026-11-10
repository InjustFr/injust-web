<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Settings\LegalDocumentView;

interface SalesDocuments
{
    public function invoicePdf(InvoiceView $invoice): string;

    public function legalPdf(LegalDocumentView $document, string $locale): string;
}
