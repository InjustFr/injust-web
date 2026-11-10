<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Documents;

use App\Application\Settings\LegalDocumentHtml;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Sales\Customer;
use App\Domain\Settings\LegalKind;
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
