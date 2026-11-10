<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Documents;

use App\Application\Settings\LegalDocumentHtml;
use App\Application\Shared\LegalMarkdown;
use App\Infrastructure\Documents\DompdfSalesDocuments;
use App\Infrastructure\Documents\SalesDocumentHtml;
use App\Tests\Support\Sales\DocumentExamples;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class DompdfSalesDocumentsTest extends KernelTestCase
{
    public function testTheInvoiceTheCreditNoteAndTheLegalDocumentArePdfFiles(): void
    {
        $documents = new DompdfSalesDocuments(new SalesDocumentHtml(
            self::getContainer()->get(Environment::class),
            DocumentExamples::queries(DocumentExamples::settings()),
            new LegalDocumentHtml(new LegalMarkdown()),
            'https://injust.test',
        ));

        $invoice = $documents->invoicePdf(DocumentExamples::invoice(DocumentExamples::paidOrder()));
        $creditNote = $documents->creditNotePdf(SalesDocumentHtmlTest::creditNote(DocumentExamples::invoiceOf(DocumentExamples::paidOrder()), 1500, null));
        $legal = $documents->legalPdf(DocumentExamples::document(), 'fr');

        self::assertStringStartsWith('%PDF-', $invoice);
        self::assertStringStartsWith('%PDF-', $creditNote);
        self::assertStringContainsString('/MediaBox [0.000 0.000 595.280 841.890]', $creditNote);
        self::assertStringStartsWith('%PDF-', $legal);
        self::assertStringContainsString('DejaVuSans', $invoice);
        self::assertStringContainsString('/MediaBox [0.000 0.000 595.280 841.890]', $invoice);
    }
}
