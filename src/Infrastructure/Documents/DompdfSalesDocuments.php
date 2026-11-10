<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Application\Sales\CreditNoteView;
use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesDocuments;
use App\Application\Settings\LegalDocumentView;
use Dompdf\Dompdf;

final readonly class DompdfSalesDocuments implements SalesDocuments
{
    public function __construct(private SalesDocumentHtml $html)
    {
    }

    public function invoicePdf(InvoiceView $invoice): string
    {
        return self::pdf($this->html->invoice($invoice));
    }

    public function creditNotePdf(CreditNoteView $creditNote): string
    {
        return self::pdf($this->html->creditNote($creditNote));
    }

    public function legalPdf(LegalDocumentView $document, string $locale): string
    {
        return self::pdf($this->html->legal($document, $locale));
    }

    public function licence(OrderView $order): string
    {
        return self::pdf($this->html->licence($order));
    }

    private static function pdf(string $html): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
