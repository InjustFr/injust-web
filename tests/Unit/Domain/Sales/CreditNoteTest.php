<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\CreditNote;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceLine;
use App\Domain\Sales\Seller;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\Price;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;

final class CreditNoteTest extends TestCase
{
    public function testACreditNoteCopiesTheInvoiceItCreditsAndOneNegativeLine(): void
    {
        $customer = SalesExamples::customer('DE', 'DE123456789', 'Riot GmbH');
        $order = SalesExamples::paid(SalesExamples::lesson(4500), $customer, '2026-11-10 10:05:00 UTC');
        $invoice = Invoice::issue('2026-0001', $order, Seller::of(DocumentExamples::identity()), new VatMentions('Franchise', 'Autoliquidation', 'Hors UE'), new \DateTimeImmutable('2026-11-10 10:06:00 UTC'));

        $note = CreditNote::issue('2026-0002', $invoice, 1500, 're_1', ' Late cancellation ', new \DateTimeImmutable('2026-11-12 09:00:00 UTC'));

        self::assertSame(['2026-0002', '2026-0001', $order->reference(), 'fr', 1500, 'Autoliquidation', 'Late cancellation', 're_1'], [$note->number(), $note->invoiceNumber(), $note->orderReference(), $note->locale(), $note->amount()->cents, $note->vatMention(), $note->reason(), $note->stripeRefundId()]);
        self::assertTrue($order->id()->equals($note->orderId()));
        self::assertFalse($note->id()->equals($invoice->id()));
        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'), $note->issuedAt());
        self::assertEquals($invoice->seller(), $note->seller());
        self::assertEquals($customer, $note->buyer());
        self::assertEquals([new InvoiceLine('Avoir sur facture 2026-0001 — Cours de guitare', 1, new Price(1500), new Price(1500))], $note->lines());
        self::assertNull($note->mailedAt());
    }

    public function testABlankReasonIsLeftOut(): void
    {
        $invoice = DocumentExamples::invoiceOf(SalesExamples::paid());

        self::assertNull(CreditNote::issue('2026-0002', $invoice, 100, 're_1', '  ', new \DateTimeImmutable())->reason());
        self::assertNull(CreditNote::issue('2026-0003', $invoice, 100, 're_2', null, new \DateTimeImmutable())->reason());
    }

    public function testTheMailingTimeIsKept(): void
    {
        $note = CreditNote::issue('2026-0002', DocumentExamples::invoiceOf(SalesExamples::paid()), 100, 're_1', null, new \DateTimeImmutable());

        $note->mailed(new \DateTimeImmutable('2026-11-12 09:00:05 UTC'));

        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:05 UTC'), $note->mailedAt());
    }
}
