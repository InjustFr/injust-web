<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Mail\Sales;

use App\Application\Sales\CreditNoteView;
use App\Application\Sales\InvoiceView;
use App\Application\Sales\SalesDocuments;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
use App\Application\Settings\SettingsView;
use App\Domain\Sales\CreditNote;
use App\Domain\Sales\Order;
use App\Infrastructure\Mail\Sales\SymfonySalesMails;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\SalesExamples;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final class CreditNoteMailTest extends KernelTestCase
{
    public function testTheFrenchCreditNoteMailTellsTheRefundAndAttachesTheCreditNote(): void
    {
        $order = DocumentExamples::paidOrder();

        self::send($order, 1500, 'Cours annulé', DocumentExamples::settings());

        $email = self::sent();
        self::assertSame('stripe@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('hello@injust.test', $email->getReplyTo()[0]->getAddress());
        self::assertSame('Injust : avoir 2026-0008 pour votre commande '.$order->reference(), $email->getSubject());
        foreach ([self::text($email->getHtmlBody()), self::text($email->getTextBody())] as $body) {
            foreach ([
                'Votre remboursement de 15,00 € pour la commande '.$order->reference().' a bien été effectué.',
                'sous 5 à 10 jours',
                'l’avoir 2026-0008, qui se rapporte à la facture 2026-0007',
                'Motif : Cours annulé',
            ] as $line) {
                self::assertStringContainsString($line, $body);
            }
        }
        self::assertSame(
            [['Avoir-2026-0008.pdf', 'application/pdf', 'credit note 2026-0008']],
            array_map(static fn (DataPart $part): array => [$part->getFilename(), $part->getMediaType().'/'.$part->getMediaSubtype(), $part->getBody()], $email->getAttachments()),
        );
    }

    public function testTheEnglishCreditNoteMailWithoutReasonRepliesToTheSeller(): void
    {
        $order = DocumentExamples::paidOrder(SalesExamples::production(), locale: 'en');

        self::send($order, 12000, null, DocumentExamples::settings(contactEmail: ''));

        $email = self::sent();
        self::assertSame('louis@injust.test', $email->getReplyTo()[0]->getAddress());
        self::assertSame('Injust: credit note 2026-0008 for your order '.$order->reference(), $email->getSubject());
        foreach ([self::text($email->getHtmlBody()), self::text($email->getTextBody())] as $body) {
            self::assertStringContainsString('Your refund of €120.00 for order '.$order->reference().' has been made.', $body);
            self::assertStringContainsString('credit note 2026-0008, which relates to invoice 2026-0007', $body);
            self::assertStringNotContainsString('Reason', $body);
        }
    }

    public function testTheReasonIsEscapedInTheHtmlPart(): void
    {
        self::send(DocumentExamples::paidOrder(), 1500, '<b>x</b>', DocumentExamples::settings());

        self::assertStringNotContainsString('<b>x</b>', (string) self::sent()->getHtmlBody());
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', (string) self::sent()->getHtmlBody());
    }

    private static function send(Order $order, int $cents, ?string $reason, SettingsView $settings): void
    {
        $note = CreditNote::issue('2026-0008', DocumentExamples::invoiceOf($order), $cents, 're_test_1', $reason, new \DateTimeImmutable('2026-12-01 09:00:00 Europe/Paris'));

        self::mails($settings)->creditNote(DocumentExamples::orderView($order), CreditNoteView::of($note));
    }

    private static function mails(SettingsView $settings): SymfonySalesMails
    {
        $documents = new class implements SalesDocuments {
            public function invoicePdf(InvoiceView $invoice): string
            {
                return 'invoice '.$invoice->number;
            }

            public function creditNotePdf(CreditNoteView $creditNote): string
            {
                return 'credit note '.$creditNote->number;
            }

            public function legalPdf(LegalDocumentView $document, string $locale): string
            {
                return 'legal';
            }
        };

        self::getContainer()->set(SettingsQueries::class, DocumentExamples::queries($settings));
        self::getContainer()->set(SalesDocuments::class, $documents);

        return self::getContainer()->get(SymfonySalesMails::class);
    }

    private static function sent(): Email
    {
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    private static function text(mixed $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) (\is_string($html) ? $html : ''))), \ENT_QUOTES | \ENT_HTML5)));
    }
}
