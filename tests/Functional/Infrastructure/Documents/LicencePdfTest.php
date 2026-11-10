<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Documents;

use App\Application\Sales\OrderView;
use App\Application\Settings\LegalDocumentHtml;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Beats\Deliverable;
use App\Domain\Sales\LicensedBeat;
use App\Domain\Sales\Order;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Infrastructure\Documents\DompdfSalesDocuments;
use App\Infrastructure\Documents\SalesDocumentHtml;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\SalesExamples;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class LicencePdfTest extends KernelTestCase
{
    public function testTheEnglishLicenceNamesThePartiesTheBeatTheTierItsCapsAndWhatIsNotTransferred(): void
    {
        $order = DocumentExamples::paidOrder(SalesExamples::beat(4900), null, 'en');

        $text = self::text(self::html()->licence(OrderView::of($order)));

        foreach ([
            'Licence agreement',
            'Order: '.$order->reference(),
            'Date: 10/11/2026',
            'Producer',
            'Louis Injust EI',
            '1 rue du Son 75000 Paris',
            'SIREN: 443061841',
            'louis@injust.test',
            'Licensee',
            'Test Buyer',
            'stripe@example.test',
            'France',
            'Beat: Riot',
            'Licence tier: Premium',
            'Non-exclusive licence',
            'Files supplied: MP3, WAV',
            'Streams: 10,000',
            'Physical copies: Unlimited',
            'Music videos: 1',
            'Scope',
            'Term: 10 years from the order date',
            'Territory: worldwide',
            'Synchronisation: music videos and online videos only',
            'Composer share to declare for the Producer: 50% of the musical work',
            'Not transferred',
            'SACEM',
            'public performance',
            'mechanical reproduction',
            'collecting society',
            'Prod. Injust',
            'Licence terms (version 2)',
            'Licence EN v2',
        ] as $line) {
            self::assertStringContainsString($line, $text);
        }
        self::assertStringNotContainsString('Licence v2', $text);
        self::assertStringContainsString('lang="en"', self::html()->licence(OrderView::of($order)));
    }

    public function testTheFrenchExclusiveLicenceHasUnlimitedCapsAndTheStems(): void
    {
        $beat = new LicensedBeat(SalesExamples::BEAT_ID, 'Riot', 'riot', SalesExamples::TIER_ID, new LocalizedText('Exclusive FR', 'Exclusive'), true, [Deliverable::Mp3, Deliverable::Wav, Deliverable::Stems], null, null, null);
        $order = DocumentExamples::paidOrder(new Purchasable(ProductType::Beat, SalesExamples::BEAT_ID, 'Riot — Exclusive FR', new Price(49900), null, $beat), SalesExamples::customer(company: 'Riot Records'));

        $text = self::text(self::html()->licence(OrderView::of($order)));

        foreach ([
            'Contrat de licence',
            'Commande : '.$order->reference(),
            'Producteur',
            'Licencié',
            'Riot Records',
            'Formule : Exclusive FR',
            'Licence exclusive',
            'Fichiers fournis : MP3, WAV, Pistes séparées (ZIP)',
            'Streams : illimité',
            'Exemplaires physiques : illimité',
            'Clips vidéo : illimité',
            'Étendue',
            'Durée : toute la durée légale de protection des droits d’auteur sur le beat',
            'Territoire : monde entier',
            'Synchronisation : clips, vidéos en ligne, films, publicités, jeux vidéo et productions audiovisuelles',
            'Part de compositeur à déclarer pour le Producteur : 50 % de l’œuvre musicale',
            'SACEM',
            'Prod. Injust',
            'Conditions de la licence (version 2)',
            'Licence v2',
        ] as $line) {
            self::assertStringContainsString($line, $text);
        }
    }

    public function testWhatTheBuyerTypedIsEscaped(): void
    {
        $order = DocumentExamples::paidOrder(SalesExamples::beat(), SalesExamples::customer(name: '<script>alert(1)</script>'));

        $html = self::html()->licence(OrderView::of($order));

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testTheLicenceIsAPdf(): void
    {
        $pdf = new DompdfSalesDocuments(self::html())->licence(OrderView::of(DocumentExamples::paidOrder(SalesExamples::beat())));

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('/MediaBox [0.000 0.000 595.280 841.890]', $pdf);
    }

    public function testAnOrderWithoutLicenceHasNoLicenceDocument(): void
    {
        $this->expectException(\LogicException::class);

        self::html()->licence(OrderView::of(self::lessonOrder()));
    }

    private static function lessonOrder(): Order
    {
        return DocumentExamples::paidOrder();
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
