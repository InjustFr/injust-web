<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Documents;

use App\Application\Sales\CreditNoteView;
use App\Application\Sales\InvoiceView;
use App\Application\Settings\LegalDocumentHtml;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Sales\CreditNote;
use App\Domain\Sales\Customer;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\Order;
use App\Domain\Sales\Seller;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\VatMentions;
use App\Infrastructure\Documents\SalesDocumentHtml;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\SalesExamples;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class SalesDocumentHtmlTest extends KernelTestCase
{
    public function testAConsumerInvoiceCarriesEveryFrenchMention(): void
    {
        $text = self::text(self::html()->invoice(DocumentExamples::invoice(DocumentExamples::paidOrder())));

        foreach ([
            'Facture 2026-0007',
            'Date d’émission : 10/11/2026',
            'Commande : INJ-',
            'Louis Injust',
            'EI',
            '1 rue du Son 75000 Paris',
            'SIREN : 443061841',
            'louis@injust.test',
            'Test Buyer',
            '1 rue du Son',
            '75000 Paris',
            'France',
            'Cours de guitare',
            'Quantité',
            'Prix unitaire',
            'Total TTC',
            '45,00 €',
            'TVA non applicable – art. 293 B du CGI',
            'Facture acquittée le 10/11/2026 par carte bancaire',
            'Catégorie de l’opération : prestation de services',
        ] as $mention) {
            self::assertStringContainsString($mention, $text);
        }
        self::assertStringNotContainsString('Pénalités de retard', $text);
        self::assertStringNotContainsString('Escompte', $text);
        self::assertStringNotContainsString('TVA intracommunautaire', $text);
    }

    public function testTheInvoiceDatesAreInParisTime(): void
    {
        $order = SalesExamples::order();
        $order->attachSession('cs_late');
        $order->pay(SalesExamples::payment($order, paidAt: '2026-12-31 23:30:00 UTC'));

        $text = self::text(self::html()->invoice(DocumentExamples::invoice($order)));

        self::assertStringContainsString('Facture acquittée le 01/01/2027 par carte bancaire', $text);
    }

    public function testAnEuBusinessInvoiceShowsTheBuyerVatIdTheReverseChargeAndTheLatePenalties(): void
    {
        $buyer = new Customer('Hans Muster', 'hans@example.test', 'DE', 'Hauptstraße 1', 'Hinterhaus', '10115', 'Berlin', null, 'Muster GmbH', 'DE123456789', 'DE');

        $text = self::text(self::html()->invoice(DocumentExamples::invoice(DocumentExamples::paidOrder(SalesExamples::production(), $buyer))));

        foreach ([
            'Muster GmbH',
            'Hans Muster',
            'Hauptstraße 1',
            'Hinterhaus',
            '10115 Berlin',
            'Allemagne',
            'TVA intracommunautaire : DE123456789',
            'Mixage',
            '120,00 €',
            'Autoliquidation',
            'Pénalités de retard : trois fois le taux d’intérêt légal. Indemnité forfaitaire pour frais de recouvrement : 40 €.',
            'Escompte pour paiement anticipé : néant.',
            'Catégorie de l’opération : prestation de services',
        ] as $mention) {
            self::assertStringContainsString($mention, $text);
        }
    }

    public function testANonEuBusinessInvoiceUsesItsMentionAndTheLatePenalties(): void
    {
        $buyer = new Customer('Jane Doe', 'jane@example.test', 'US', '1 Main St', '', '10001', 'New York', 'NY', 'Doe LLC', null, 'US');

        $text = self::text(self::html()->invoice(DocumentExamples::invoice(DocumentExamples::paidOrder(SalesExamples::production(), $buyer))));

        self::assertStringContainsString('TVA non applicable – art. 259-1 du CGI', $text);
        self::assertStringContainsString('10001 New York NY', $text);
        self::assertStringContainsString('États-Unis', $text);
        self::assertStringContainsString('Pénalités de retard', $text);
    }

    public function testAFrenchCompanyIsABusinessBuyer(): void
    {
        $buyer = SalesExamples::customer(company: 'Studio SARL');

        $text = self::text(self::html()->invoice(DocumentExamples::invoice(DocumentExamples::paidOrder(customer: $buyer))));

        self::assertStringContainsString('Studio SARL', $text);
        self::assertStringContainsString('TVA non applicable – art. 293 B du CGI', $text);
        self::assertStringContainsString('Pénalités de retard', $text);
    }

    public function testEnglishLabelsFollowTheFrenchOnes(): void
    {
        $text = self::text(self::html()->invoice(DocumentExamples::invoice(DocumentExamples::paidOrder(locale: 'en'))));

        self::assertStringContainsString('Facture Invoice 2026-0007', $text);
        self::assertStringContainsString('Paid on 10/11/2026 by card', $text);
    }

    public function testWhatTheBuyerTypedIsEscaped(): void
    {
        $buyer = SalesExamples::customer(name: '<script>alert(1)</script>');

        $html = self::html()->invoice(DocumentExamples::invoice(DocumentExamples::paidOrder(customer: $buyer)));

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testTheLegalDocumentIsRenderedInTheRequestedLocaleWithItsVersion(): void
    {
        $document = DocumentExamples::document(LegalKind::CgvProduction, "# CGV\n\nVendeur : {name}, SIREN {siren}. <b>x</b>", "# Terms\n\nSeller: {name}.", edits: 2);

        $french = self::html()->legal($document, 'fr');
        $english = self::text(self::html()->legal($document, 'en'));

        self::assertStringContainsString('<h1>CGV</h1>', $french);
        self::assertStringContainsString('Vendeur : Louis Injust, SIREN 443061841. &lt;b&gt;x&lt;/b&gt;', $french);
        self::assertStringContainsString('Conditions générales de vente – production', self::text($french));
        self::assertStringContainsString('Version 3 – 02/11/2026', self::text($french));
        self::assertStringContainsString('lang="fr"', $french);
        self::assertStringContainsString('Seller: Louis Injust.', $english);
        self::assertStringContainsString('Terms of sale – production', $english);
        self::assertStringContainsString('Version 3 – 02/11/2026', $english);
    }

    public function testTheSellerVatNumberIsOnTheInvoiceWhenSet(): void
    {
        $text = self::text(self::html()->invoice(InvoiceView::of(self::invoiceWithVatNumber())));

        self::assertStringContainsString('SIREN : 443061841 TVA intracommunautaire : FR12443061841', $text);
    }

    public function testACreditNoteCreditsItsInvoiceWithANegativeAmount(): void
    {
        $text = self::text(self::html()->creditNote(self::creditNote(DocumentExamples::invoiceOf(DocumentExamples::paidOrder()), 1500, 'Cours annulé')));

        foreach ([
            'Avoir 2026-0008',
            'Date d’émission : 01/12/2026',
            'Avoir sur la facture n° 2026-0007',
            'Commande : INJ-',
            'Louis Injust',
            'SIREN : 443061841',
            'Test Buyer',
            'France',
            'Avoir sur facture 2026-0007 — Cours de guitare',
            '-15,00 €',
            'Total TTC Total -15,00 €',
            'TVA non applicable – art. 293 B du CGI',
            'Remboursement effectué le 01/12/2026 par carte bancaire',
            'Motif Reason : Cours annulé',
        ] as $mention) {
            self::assertStringContainsString($mention, $text);
        }
        self::assertStringNotContainsString('TVA intracommunautaire', $text);
        self::assertStringNotContainsString(' 15,00 €', str_replace('-15,00 €', '', $text));
    }

    public function testABusinessCreditNoteKeepsTheReverseChargeMentionAndBothVatNumbers(): void
    {
        $buyer = new Customer('Hans Muster', 'hans@example.test', 'DE', 'Hauptstraße 1', '', '10115', 'Berlin', null, 'Muster GmbH', 'DE123456789', 'DE');
        $invoice = self::invoiceWithVatNumber(DocumentExamples::paidOrder(SalesExamples::production(), $buyer, 'en'));

        $text = self::text(self::html()->creditNote(self::creditNote($invoice, 12000, null)));

        foreach (['Avoir Credit note 2026-0008', 'Muster GmbH', 'Allemagne', 'TVA intracommunautaire : DE123456789', 'TVA intracommunautaire : FR12443061841', 'Autoliquidation', '-120,00 €', 'Refunded on 01/12/2026 by card'] as $mention) {
            self::assertStringContainsString($mention, $text);
        }
        self::assertStringNotContainsString('Motif', $text);
    }

    public function testTheCreditNoteEscapesTheReason(): void
    {
        $html = self::html()->creditNote(self::creditNote(DocumentExamples::invoiceOf(DocumentExamples::paidOrder()), 1500, '<b>x</b>'));

        self::assertStringNotContainsString('<b>x</b>', $html);
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    public static function creditNote(Invoice $invoice, int $cents, ?string $reason): CreditNoteView
    {
        return CreditNoteView::of(CreditNote::issue('2026-0008', $invoice, $cents, 're_test_1', $reason, new \DateTimeImmutable('2026-12-01 09:00:00 Europe/Paris')));
    }

    private static function invoiceWithVatNumber(?Order $order = null): Invoice
    {
        return Invoice::issue('2026-0007', $order ?? DocumentExamples::paidOrder(), Seller::of(DocumentExamples::identity('FR12443061841')), VatMentions::defaults(), new \DateTimeImmutable('2026-11-10 10:06:00'));
    }

    private static function html(): SalesDocumentHtml
    {
        return new SalesDocumentHtml(
            self::getContainer()->get(Environment::class),
            DocumentExamples::queries(DocumentExamples::settings()),
            new LegalDocumentHtml(new LegalMarkdown()),
            'https://injust.test',
        );
    }

    private static function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), \ENT_QUOTES | \ENT_HTML5)));
    }
}
