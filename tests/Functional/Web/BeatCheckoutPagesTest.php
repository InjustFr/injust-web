<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Beats\Deliverable;
use App\Domain\Settings\LegalKind;
use App\Tests\Support\Json;
use App\Tests\Support\Payments\SellsBeats;
use App\Tests\Support\Payments\SellsOnline;
use App\Tests\Support\Sales\SalesExamples;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

final class BeatCheckoutPagesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use SellsBeats;
    use SellsOnline;

    public function testTheBeatCheckoutPageSummarisesTheTierAndAsksForTheThreeConsents(): void
    {
        $client = self::shop();
        self::openShop(beats: true);
        self::writeLegal(LegalKind::Licence, 'Licence', 'Licence');
        $tier = self::tier('Premium', 5900, [Deliverable::Mp3, Deliverable::Wav]);
        $beat = self::beatOnSale();

        $crawler = $client->request('GET', '/checkout/beat/'.$beat->id()->toBase32().'?tier='.$tier->id()->toBase32());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('Riot — Premium', $crawler->filter('.checkout-product')->text());
        self::assertSame(['140 BPM · A minor', 'Files: MP3, WAV', 'Streams: 10,000', 'Copies: Unlimited', 'Music videos: 1', 'Exclusive rights: No'], $crawler->filter('.checkout-details li')->each(static fn (Crawler $item): string => $item->text()));
        self::assertSame('€59', $crawler->filter('.checkout-price')->text());
        $props = Json::decode((string) $crawler->filter('[data-island="CheckoutForm"]')->attr('data-props'));
        self::assertSame(['productType' => 'beat', 'productId' => $beat->id()->toBase32(), 'locale' => 'en', 'cgvVersion' => 1, 'tierId' => $tier->id()->toBase32(), 'licenceVersion' => 1], Json::at($props, 'product'));
        self::assertSame(['terms', 'digital-waiver', 'licence'], array_map(static fn (mixed $consent): string => Json::string($consent, 'kind'), Json::array($props, 'consents')));
        self::assertStringContainsString('(version 1)', Json::string($props, 'consents', 2, 'text'));
    }

    public function testABeatWithoutAPurchasableTierHasNoCheckoutPage(): void
    {
        $client = self::shop();
        self::openShop(beats: true);
        self::writeLegal(LegalKind::Licence, 'Licence', 'Licence');
        $exclusive = self::tier('Exclusive', 49900, [Deliverable::Stems], true);
        $beat = self::beatOnSale()->id()->toBase32();

        foreach (['/checkout/beat/'.$beat, '/checkout/beat/'.$beat.'?tier='.$exclusive->id()->toBase32(), '/fr/checkout/beat/'.$beat.'?tier=nope'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testThePaidBeatOrderShowsItsDownloadsAndTheLicence(): void
    {
        $client = self::shop();
        self::editGeneral();
        self::mockTime('2026-11-11 09:00:00');
        $order = self::storeOrder(SalesExamples::paid(SalesExamples::beat(), session: 'cs_paid'));
        $id = $order->id()->toBase32();

        $crawler = $client->request('GET', '/checkout/success?order='.$id);

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('.checkout-downloads a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]);
        self::assertCount(3, $links);
        self::assertSame(['Download MP3', 'Download WAV', 'Licence (PDF)'], array_column($links, 0));
        foreach (['mp3', 'wav', 'licence'] as $index => $file) {
            self::assertMatchesRegularExpression('#^http://localhost/download/'.$id.'/'.$file.'\?_expiration=\d+&_hash=[\w-]+$#', $links[$index][1]);
        }
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringNotContainsString('public', $cacheControl);
    }

    public function testDownloadsAreOfferedOnTheSuccessPageOnlyDuringTheSevenDaysOfTheMailedLinks(): void
    {
        $client = self::shop();
        $order = self::storeOrder(SalesExamples::paid(SalesExamples::beat(), paidAt: '2026-11-10 10:05:00', session: 'cs_old'));
        $url = '/checkout/success?order='.$order->id()->toBase32();

        self::mockTime('2026-11-17 10:04:00');
        $crawler = $client->request('GET', $url);
        self::assertCount(3, $crawler->filter('.checkout-downloads a'));

        self::mockTime('2026-11-17 10:05:00');
        $crawler = $client->request('GET', $url);
        self::assertCount(0, $crawler->filter('.checkout-downloads a'));
        self::assertStringNotContainsString('/download/', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Download links are valid for 7 days. Reply to your confirmation email', $crawler->filter('main')->text());
    }

    public function testAPendingOrRefundedBeatOrderShowsNoDownload(): void
    {
        $client = self::shop();
        $pending = SalesExamples::order(SalesExamples::beat());
        $pending->attachSession('cs_pending');
        $refunded = SalesExamples::paid(SalesExamples::beat(), session: 'cs_refunded');
        $refunded->refund(3000);

        foreach ([self::storeOrder($pending), self::storeOrder($refunded)] as $order) {
            $crawler = $client->request('GET', '/checkout/success?order='.$order->id()->toBase32());

            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('.checkout-downloads'));
            self::assertStringNotContainsString('/download/', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('Reply to your confirmation email', $crawler->filter('main')->text());
        }
    }

    public function testTheBeatReturnPagesLeadBackToTheSameTier(): void
    {
        $client = self::shop();
        $order = SalesExamples::order(SalesExamples::beat());
        $order->attachSession('cs_cancel');
        self::storeOrder($order);
        $expired = SalesExamples::order(SalesExamples::beat());
        $expired->attachSession('cs_expired');
        $expired->expire();
        self::storeOrder($expired);
        $tierPath = '/checkout/beat/'.SalesExamples::BEAT_ID.'?tier='.SalesExamples::TIER_ID;

        $crawler = $client->request('GET', '/checkout/cancel?order='.$order->id()->toBase32());
        self::assertSame([$tierPath, '/beats'], $crawler->filter('.checkout-actions a')->each(static fn (Crawler $link): ?string => $link->attr('href')));

        $crawler = $client->request('GET', '/checkout/success?order='.$expired->id()->toBase32());
        self::assertSame($tierPath, $crawler->filter('.checkout-actions a')->attr('href'));
    }

    public function testWithOnlineCheckoutEachBeatIsBoughtThroughItsCheapestLicenceWithoutJavaScript(): void
    {
        $client = self::shop();
        self::openShop(beats: true);
        $premium = self::tier('Premium', 5900, [Deliverable::Mp3, Deliverable::Wav]);
        $basic = self::tier('Basic', 2999, [Deliverable::Mp3], position: 1);
        $exclusive = self::tier('Exclusive', 49900, [Deliverable::Stems], true, 2);
        $riot = self::beatOnSale()->id()->toBase32();
        $burn = self::beatOnSale([], slug: 'burn')->id()->toBase32();
        $buy = '/checkout/beat/'.$riot.'?tier=';

        $crawler = $client->request('GET', '/beats');

        self::assertSame([['Buy', $buy.$basic->id()->toBase32()], ['Contact me', '/contact?topic=beats&offer='.$burn]], $crawler->filter('.beat-row .beat-action a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
        $props = Json::decode((string) $crawler->filter('.beat-catalogue')->attr('data-props'));
        self::assertTrue($props['checkout']);
        self::assertSame([$premium->id()->toBase32() => $buy.$premium->id()->toBase32(), $basic->id()->toBase32() => $buy.$basic->id()->toBase32(), $exclusive->id()->toBase32() => null], Json::at(Json::array(Json::array($props, 'rows'), 0), 'licences'));
        self::assertSame([null, null, null], array_values(Json::array(Json::array(Json::array($props, 'rows'), 1), 'licences')));
        self::assertSame(['Premium', 'Basic', 'Exclusive'], array_map(static fn (mixed $tier): string => Json::string($tier, 'name'), Json::array($props, 'tiers')));
        $picker = Json::array(Json::array($props, 'labels'), 'picker');
        self::assertSame(['locale' => 'en', 'title' => 'Choose a licence', 'close' => 'Close', 'unavailable' => 'File not available'], array_intersect_key($picker, array_flip(['title', 'close', 'unavailable', 'locale'])));
        self::assertSame('Buy', Json::at($props, 'labels', 'buy'));

        $crawler = $client->request('GET', '/fr/beats');

        self::assertSame(['Acheter', '/fr/checkout/beat/'.$riot.'?tier='.$basic->id()->toBase32()], [$crawler->filter('.beat-row .beat-action a')->text(), $crawler->filter('.beat-row .beat-action a')->attr('href')]);
    }

    public function testWithCheckoutOffTheBeatsAreStillSoldThroughTheContactForm(): void
    {
        $client = self::shop();
        self::openShop(checkout: false, beats: true);
        self::tier('Basic', 2999, [Deliverable::Mp3]);
        $riot = self::beatOnSale()->id()->toBase32();

        $crawler = $client->request('GET', '/beats');

        self::assertSame('/contact?topic=beats&offer='.$riot, $crawler->filter('.beat-row .beat-action a')->attr('href'));
        self::assertFalse(Json::decode((string) $crawler->filter('.beat-catalogue')->attr('data-props'))['checkout']);
    }

    public function testTheBeatCheckoutPageSwitchesBetweenTheLicences(): void
    {
        $client = self::shop();
        self::openShop(beats: true);
        self::writeLegal(LegalKind::Licence, 'Licence', 'Licence');
        $basic = self::tier('Basic', 2999, [Deliverable::Mp3]);
        $premium = self::tier('Premium', 5900, [Deliverable::Mp3, Deliverable::Wav], position: 1);
        self::tier('Exclusive', 49900, [Deliverable::Stems], true, 2);
        $beat = self::beatOnSale()->id()->toBase32();

        $crawler = $client->request('GET', '/fr/checkout/beat/'.$beat.'?tier='.$premium->id()->toBase32());

        $options = $crawler->filter('.checkout-licences li');
        self::assertSame('Licence', $crawler->filter('.checkout-licences')->attr('aria-label'));
        self::assertSame(["Basic FR 29,99\u{a0}€", "Premium FR 59\u{a0}€", "Exclusive FR 499\u{a0}€ Fichier indisponible"], $options->each(static fn (Crawler $option): string => $option->text()));
        self::assertSame('/fr/checkout/beat/'.$beat.'?tier='.$basic->id()->toBase32(), $options->eq(0)->filter('a')->attr('href'));
        self::assertSame('page', $options->eq(1)->filter('a')->attr('aria-current'));
        self::assertCount(0, $options->eq(2)->filter('a'));
        self::assertSame('/checkout/beat/'.$beat.'?tier='.$premium->id()->toBase32(), $crawler->filter('a.lang-switch')->attr('href'));
        $props = Json::decode((string) $crawler->filter('[data-island="CheckoutForm"]')->attr('data-props'));
        self::assertSame(['href' => '/fr/legal/licence', 'label' => 'Lire les conditions de la licence (nouvel onglet)'], Json::at($props, 'licence'));
        self::assertSame(['J’ai lu et j’accepte les conditions générales de vente (version 1).', 'Je demande expressément la fourniture immédiate des fichiers dès la confirmation de mon paiement et je reconnais perdre ainsi mon droit de rétractation.', 'J’accepte les conditions de la licence (version 1) pour la formule choisie, y compris ses limites de streams, de copies et de clips.'], array_map(static fn (mixed $consent): string => Json::string($consent, 'text'), Json::array($props, 'consents')));
    }
}
