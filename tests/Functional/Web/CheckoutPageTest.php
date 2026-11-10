<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Lessons\AddLessonOffer\AddLessonOffer;
use App\Application\Lessons\AddLessonOffer\AddLessonOfferHandler;
use App\Application\Production\AddProductionService\AddProductionService;
use App\Application\Production\AddProductionService\AddProductionServiceHandler;
use App\Application\Settings\SwitchOnlineCheckout\SwitchOnlineCheckout;
use App\Application\Settings\SwitchOnlineCheckout\SwitchOnlineCheckoutHandler;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class CheckoutPageTest extends WebTestCase
{
    use ConfiguresSite;
    use SignsInClient;

    public function testTheLessonsKeepTheContactLinkWhileCheckoutIsOff(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        $offer = self::lesson();

        $crawler = $client->request('GET', '/lessons');

        self::assertSame(['Contact me'], self::texts($crawler, '.offer a.button'));
        self::assertSame('/contact?topic=lessons&offer='.$offer, $crawler->filter('.offer a.button')->attr('href'));
    }

    public function testTheLessonsOfferBookAndPayOnceCheckoutIsOn(): void
    {
        $client = self::createClient();
        self::openShop(true, false);
        $offer = self::lesson();
        $free = self::lesson('Trial', 0);

        $crawler = $client->request('GET', '/lessons');

        self::assertSame(['Book & pay', 'Contact me'], self::texts($crawler, '.offer a.button'));
        self::assertSame(['/checkout/lesson/'.$offer, '/contact?topic=lessons&offer='.$free], self::links($crawler, '.offer a.button'));
        self::assertSame('offer-'.$offer, $crawler->filter('.offer a.button')->first()->attr('aria-describedby'));

        $crawler = $client->request('GET', '/fr/lessons');

        self::assertSame('Réserver et payer', $crawler->filter('.offer a.button')->first()->text());
        self::assertSame('/fr/checkout/lesson/'.$offer, $crawler->filter('.offer a.button')->first()->attr('href'));
    }

    public function testOnlyTheFixedPriceServicesOfferBookAndPay(): void
    {
        $client = self::createClient();
        self::openShop(false, true);
        $mix = self::service('Mix', Pricing::Fixed, 12000);
        $production = self::service('Production', Pricing::Quote, 8000);

        $crawler = $client->request('GET', '/production');

        self::assertSame(['Book & pay', 'Request a quote'], self::texts($crawler, '.offer a.button'));
        self::assertSame(['/checkout/production/'.$mix, '/contact?topic=production&offer='.$production], self::links($crawler, '.offer a.button'));
    }

    public function testTheServicesKeepTheContactLinkWhileCheckoutIsOff(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        $mix = self::service('Mix', Pricing::Fixed, 12000);

        $crawler = $client->request('GET', '/production');

        self::assertSame(['Contact me'], self::texts($crawler, '.offer a.button'));
        self::assertSame(['/contact?topic=production&offer='.$mix], self::links($crawler, '.offer a.button'));
    }

    public function testTheLessonCheckoutSummarisesTheOfferAndAsksForTheServiceConsents(): void
    {
        $client = self::createClient();
        self::openShop(true, false);
        self::writeLegal(LegalKind::CgvLessons, 'CGV cours v2');
        $offer = self::lesson();

        $crawler = $client->request('GET', '/checkout/lesson/'.$offer);

        self::assertResponseIsSuccessful();
        self::assertSame('Checkout | Injust', $crawler->filter('title')->text());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
        self::assertSame('Online pack', $crawler->filter('.checkout-product')->text());
        self::assertSame(['Pack of 4', 'Online', '4 × 45 min'], self::texts($crawler, '.checkout-details li'));
        self::assertSame('€250.01', $crawler->filter('.checkout-price')->text());
        self::assertSame('All taxes included. TVA non applicable – art. 293 B du CGI', $crawler->filter('.checkout-tax')->text());
        self::assertSame('page', $crawler->filter('header nav a.nav-section')->attr('aria-current'));
        $props = self::props($crawler);
        self::assertSame('/api/checkout', $props['endpoint']);
        self::assertSame(['productType' => 'lesson', 'productId' => $offer, 'locale' => 'en', 'cgvVersion' => 2], $props['product']);
        self::assertSame([
            ['kind' => 'terms', 'text' => 'I have read and accept the terms of sale (version 2).'],
            ['kind' => 'early-start', 'text' => 'I expressly request that the service starts before the end of the 14-day withdrawal period.'],
            ['kind' => 'pro-rata', 'text' => 'I acknowledge that if I withdraw after the service has started, I will pay an amount proportional to the service already provided.'],
        ], $props['consents']);
        self::assertSame(['href' => '/legal/cgv-lessons', 'label' => 'Read the terms of sale, version 2 (opens in a new tab)'], $props['terms']);
        self::assertSame('Order with obligation to pay', Json::string($props, 'labels', 'submit'));
        self::assertSame('Email', Json::string($props, 'labels', 'email'));
        self::assertCount(3, $crawler->filter('.checkout-next li'));
    }

    public function testTheFrenchCheckoutShowsTheBindingFrenchSentences(): void
    {
        $client = self::createClient();
        self::openShop(true, false);
        $offer = self::lesson();

        $crawler = $client->request('GET', '/fr/checkout/lesson/'.$offer);

        self::assertSame("250,01\u{a0}€", $crawler->filter('.checkout-price')->text());
        self::assertSame('TTC. TVA non applicable – art. 293 B du CGI', $crawler->filter('.checkout-tax')->text());
        $props = self::props($crawler);
        self::assertSame('fr', Json::string($props, 'product', 'locale'));
        self::assertSame([
            'J’ai lu et j’accepte les conditions générales de vente (version 1).',
            'Je demande expressément que la prestation commence avant la fin du délai de rétractation de 14 jours.',
            'Je reconnais que si j’exerce mon droit de rétractation après le début de la prestation, je paierai un montant proportionnel au service déjà fourni.',
        ], array_column(Json::array($props, 'consents'), 'text'));
        self::assertSame('/fr/legal/cgv-lessons', Json::string($props, 'terms', 'href'));
        self::assertSame('Commande avec obligation de paiement', Json::string($props, 'labels', 'submit'));
    }

    public function testTheServiceCheckoutShowsTheDeliveryAndTheProductionTerms(): void
    {
        $client = self::createClient();
        self::openShop(false, true);
        $mix = self::service('Mix', Pricing::Fixed, 12000, 5, 2);

        $crawler = $client->request('GET', '/checkout/production/'.$mix);

        self::assertResponseIsSuccessful();
        self::assertSame('Mix', $crawler->filter('.checkout-product')->text());
        self::assertSame(['Mixing', 'Delivery in 5 days', '2 revisions'], self::texts($crawler, '.checkout-details li'));
        self::assertSame('€120', $crawler->filter('.checkout-price')->text());
        $props = self::props($crawler);
        self::assertSame(['terms', 'early-start', 'pro-rata'], array_column(Json::array($props, 'consents'), 'kind'));
        self::assertSame('/legal/cgv-production', Json::string($props, 'terms', 'href'));
        self::assertSame('production', Json::string($props, 'product', 'productType'));
    }

    public function testAServiceWithoutDeliveryOrRevisionsOnlyShowsItsKind(): void
    {
        $client = self::createClient();
        self::openShop(false, true);
        $mix = self::service('Quick mix', Pricing::Fixed, 4000);

        $crawler = $client->request('GET', '/checkout/production/'.$mix);

        self::assertSame(['Mixing'], self::texts($crawler, '.checkout-details li'));
    }

    public function testTheCheckoutIsNotFoundWhileCheckoutIsOff(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        self::writeLegal(LegalKind::CgvLessons, 'CGV cours');
        $offer = self::lesson();

        $client->request('GET', '/checkout/lesson/'.$offer);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheCheckoutIsNotFoundWhenTheSectionIsOffEvenForAnAdmin(): void
    {
        $client = self::signedInClient();
        self::openShop(false, true);
        $offer = self::lesson();

        $client->request('GET', '/checkout/lesson/'.$offer);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheCheckoutIsNotFoundForAProductThatCannotBeSold(): void
    {
        $client = self::createClient();
        self::openShop(true, true);
        $quote = self::service('Production', Pricing::Quote, 8000);
        $free = self::lesson('Trial', 0);
        self::writeLegal(LegalKind::CgvBeats, 'CGV beats');

        foreach (['/checkout/production/'.$quote, '/checkout/lesson/'.$free, '/checkout/lesson/01JBN5Z7Q3X0000000000000AA', '/checkout/lesson/nope', '/checkout/beat/01JBN5Z7Q3X0000000000000AA', '/checkout/shirt/'.$free, '/fr/checkout/production/'.$quote] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testTheCheckoutIsNotFoundWithoutTermsOfSale(): void
    {
        $client = self::createClient();
        self::openShop(false, true, false);
        $mix = self::service('Mix', Pricing::Fixed, 12000);

        $client->request('GET', '/checkout/production/'.$mix);

        self::assertResponseStatusCodeSame(404);
    }

    private static function openShop(bool $lessons, bool $production, bool $terms = true): void
    {
        self::switchSections($lessons, $production, false);
        self::editGeneral(true);
        self::editIdentity();
        self::getContainer()->get(SwitchOnlineCheckoutHandler::class)(new SwitchOnlineCheckout(true));
        if ($terms) {
            self::writeLegal(LegalKind::CgvLessons, 'CGV cours', 'Lessons terms');
            self::writeLegal(LegalKind::CgvProduction, 'CGV production', 'Production terms');
        }
    }

    /** @return array<array-key, mixed> */
    private static function props(Crawler $crawler): array
    {
        return Json::decode((string) $crawler->filter('[data-island="CheckoutForm"]')->attr('data-props'));
    }

    /** @return list<string> */
    private static function texts(Crawler $crawler, string $selector): array
    {
        return $crawler->filter($selector)->each(static fn (Crawler $node): string => $node->text());
    }

    /** @return list<?string> */
    private static function links(Crawler $crawler, string $selector): array
    {
        return $crawler->filter($selector)->each(static fn (Crawler $node): ?string => $node->attr('href'));
    }

    private static function lesson(string $title = 'Online pack', int $cents = 25001): string
    {
        return self::getContainer()->get(AddLessonOfferHandler::class)(new AddLessonOffer(new LessonOfferDetails(
            new LocalizedText($title, $title),
            new LocalizedText(''),
            OfferKind::Pack,
            4,
            LessonFormat::Online,
            45,
            new Price($cents),
        )))->id;
    }

    private static function service(string $title, Pricing $pricing, int $cents, ?int $deliveryDays = null, ?int $revisions = null): string
    {
        return self::getContainer()->get(AddProductionServiceHandler::class)(new AddProductionService(new ProductionServiceDetails(
            new LocalizedText($title, $title),
            new LocalizedText(''),
            ServiceKind::Mix,
            $pricing,
            new Price($cents),
            $deliveryDays,
            $revisions,
        )))->id;
    }
}
