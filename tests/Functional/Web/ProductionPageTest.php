<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Production\AddPortfolioItem\AddPortfolioItem;
use App\Application\Production\AddPortfolioItem\AddPortfolioItemHandler;
use App\Application\Production\AddProductionService\AddProductionService;
use App\Application\Production\AddProductionService\AddProductionServiceHandler;
use App\Application\Production\EditProductionPage\EditProductionPage;
use App\Application\Production\EditProductionPage\EditProductionPageHandler;
use App\Application\Production\UploadPortfolioCover\UploadPortfolioCover;
use App\Application\Production\UploadPortfolioCover\UploadPortfolioCoverHandler;
use App\Domain\Production\PortfolioItemDetails;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Ulid;

final class ProductionPageTest extends WebTestCase
{
    use ConfiguresSite;
    use SignsInClient;

    public function testTheServicesShowTheirPriceDetailsAndAContactLink(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        $mix = self::service('Mix', ServiceKind::Mix, Pricing::Fixed, 12000, 'Loud and **clear**.', 5, 2);
        $production = self::service('Full production', ServiceKind::Production, Pricing::Quote, 8050, deliveryDays: 1, revisions: 0);
        $recording = self::service('Recording', ServiceKind::Recording, Pricing::Quote, 0, revisions: 1);

        $crawler = $client->request('GET', '/production');

        self::assertResponseIsSuccessful();
        self::assertSame('Production', $crawler->filter('main h1')->text());
        self::assertSame('Music production', $crawler->filter('.page-kicker')->text());
        self::assertSame(['Mix', 'Full production', 'Recording'], self::texts($crawler, '.offer-title'));
        self::assertSame(['Mixing', 'Production', 'Recording'], self::texts($crawler, '.offer-kind'));
        self::assertSame(['Delivery in 5 days', 'Delivery in 1 day'], self::texts($crawler, '.offer-delivery'));
        self::assertSame(['2 revisions', 'No revision', '1 revision'], self::texts($crawler, '.offer-revisions'));
        self::assertSame(['€120', 'from €80.50', 'On quote'], self::texts($crawler, '.offer-price'));
        self::assertSame('clear', $crawler->filter('.offer-description strong')->text());
        self::assertCount(1, $crawler->filter('.offer-description'));
        self::assertSame(['Contact me', 'Request a quote', 'Request a quote'], self::texts($crawler, '.offer a.button'));
        self::assertSame(
            ['/contact?topic=production&offer='.$mix, '/contact?topic=production&offer='.$production, '/contact?topic=production&offer='.$recording],
            $crawler->filter('.offer a.button')->each(static fn (Crawler $link): ?string => $link->attr('href')),
        );
        self::assertSame('offer-'.$mix, $crawler->filter('.offer a.button')->first()->attr('aria-describedby'));
        self::assertCount(1, $crawler->filter('#offer-'.$mix));
        self::assertCount(0, $crawler->filter('.offers-empty'));
        self::assertCount(0, $crawler->filter('.offer-meta')->last()->filter('.offer-delivery'));
    }

    public function testAServiceWithoutDeliveryOrRevisionsHasNoMetaLine(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        self::service('Master', ServiceKind::Master, Pricing::Fixed, 4000);

        $crawler = $client->request('GET', '/production');

        self::assertSame('Mastering', $crawler->filter('.offer-kind')->text());
        self::assertCount(0, $crawler->filter('.offer-meta'));
    }

    public function testTheFrenchPageFormatsPricesAndFallsBackToTheOtherLanguage(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        $mix = self::service('Mix only', ServiceKind::MixMaster, Pricing::Fixed, 12000, deliveryDays: 3, revisions: 2, en: true);
        self::service('Production', ServiceKind::Production, Pricing::Quote, 8000);
        self::service('Arrangement', ServiceKind::Arrangement, Pricing::Quote, 0);

        $crawler = $client->request('GET', '/fr/production');

        self::assertSame('Production musicale', $crawler->filter('.page-kicker')->text());
        self::assertSame(['Mix only', 'Production', 'Arrangement'], self::texts($crawler, '.offer-title'));
        self::assertSame(['Mix + master', 'Production', 'Arrangement'], self::texts($crawler, '.offer-kind'));
        self::assertSame(['Livré en 3 jours'], self::texts($crawler, '.offer-delivery'));
        self::assertSame(['2 retouches'], self::texts($crawler, '.offer-revisions'));
        self::assertSame(["120\u{a0}€", "à partir de 80\u{a0}€", 'Sur devis'], self::texts($crawler, '.offer-price'));
        self::assertSame(['Me contacter', 'Demander un devis', 'Demander un devis'], self::texts($crawler, '.offer a.button'));
        self::assertSame('/fr/contact?topic=production&offer='.$mix, $crawler->filter('.offer a.button')->attr('href'));
    }

    public function testTheTextsAreEscapedMarkdown(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        self::text("Mixes that **hit**.\n\n<script>alert(1)</script>", "1. Send stems\n2. Get a mix", "### How long?\n\nA week.\n\n### Stems?\n\nWAV, **24 bit**.");

        $crawler = $client->request('GET', '/production');

        self::assertSame('hit', $crawler->filter('.page-intro strong')->text());
        self::assertCount(0, $crawler->filter('main script'));
        self::assertStringContainsString('&lt;script&gt;', $crawler->filter('.page-intro')->html());
        self::assertCount(0, $crawler->filter('.page-lead'));
        self::assertSame('How it works', $crawler->filter('#process-title')->text());
        self::assertSame(['Send stems', 'Get a mix'], self::texts($crawler, '.process-body ol li'));
        self::assertSame(['How long?', 'Stems?'], self::texts($crawler, '.faq details summary'));
        self::assertSame('24 bit', $crawler->filter('.faq details')->last()->filter('.faq-answer strong')->text());
        self::assertCount(0, $crawler->filter('.faq-body'));
    }

    public function testAFaqThatIsNotOnlyQuestionsIsShownAsIs(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        self::text('', '', "Read this first.\n\n### Stems?\n\nWAV.", en: true);

        $crawler = $client->request('GET', '/fr/production');

        self::assertSame('Questions fréquentes', $crawler->filter('#faq-title')->text());
        self::assertSame('Stems?', $crawler->filter('.faq-body h3')->text());
        self::assertCount(0, $crawler->filter('.faq details'));
        self::assertCount(0, $crawler->filter('.process'));
    }

    public function testWithoutContentThePageSaysSoAndPointsToTheContactForm(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);

        $crawler = $client->request('GET', '/production');

        self::assertResponseIsSuccessful();
        self::assertSame('Mixing, mastering, production and arrangement for bands and artists who want it loud and honest.', $crawler->filter('.page-lead')->text());
        self::assertCount(0, $crawler->filter('.page-intro'));
        self::assertCount(0, $crawler->filter('.offer'));
        self::assertSame('/contact?topic=production', $crawler->filter('.offers-empty a')->attr('href'));
        self::assertCount(0, $crawler->filter('.process'));
        self::assertCount(0, $crawler->filter('.portfolio'));
        self::assertCount(0, $crawler->filter('.faq'));
    }

    public function testThePortfolioShowsEachWorkWithItsCoverRolesAndListenLink(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        $covered = self::work('Burn It Down', 'The Rioters', [ServiceKind::Mix, ServiceKind::Master], 2025, 'https://listen.test/burn');
        self::getContainer()->get(UploadPortfolioCoverHandler::class)(new UploadPortfolioCover(Ulid::fromString($covered), \dirname(__DIR__, 2).'/Fixtures/large-fern.png'));
        self::work('No Gods', 'Static Hearts', [ServiceKind::Recording], null, null);

        $crawler = $client->request('GET', '/production');

        self::assertSame('Work', $crawler->filter('#portfolio-title')->text());
        self::assertSame(['Burn It Down', 'No Gods'], self::texts($crawler, '.work-title'));
        self::assertSame(['The Rioters', 'Static Hearts'], self::texts($crawler, '.work-artist'));
        self::assertSame(['Mixing · Mastering', 'Recording'], self::texts($crawler, '.work-roles'));
        self::assertSame(['2025'], self::texts($crawler, '.work-year'));
        $cover = (string) $crawler->filter('.work-cover img')->attr('src');
        self::assertMatchesRegularExpression('#^/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', $cover);
        self::assertSame('', $crawler->filter('.work-cover img')->attr('alt'));
        self::assertSame('No Gods', $crawler->filter('.work')->last()->filter('.cover-blank')->text());
        self::assertSame('true', $crawler->filter('.work')->last()->filter('.cover-blank')->attr('aria-hidden'));
        $link = $crawler->filter('.work a.work-link');
        self::assertCount(1, $link);
        self::assertSame('https://listen.test/burn', $link->attr('href'));
        self::assertSame('noopener', $link->attr('rel'));
        self::assertSame('_blank', $link->attr('target'));
        self::assertSame('Listen to “Burn It Down” by The Rioters (opens in a new tab)', $link->attr('aria-label'));
    }

    public function testTheFrenchPortfolioTranslatesTheRoles(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        self::work('Burn', 'Riot', [ServiceKind::Other, ServiceKind::Arrangement], 1990, 'https://listen.test/b');

        $crawler = $client->request('GET', '/fr/production');

        self::assertSame('Références', $crawler->filter('#portfolio-title')->text());
        self::assertSame('Autre · Arrangement', $crawler->filter('.work-roles')->text());
        self::assertSame('Écouter « Burn » de Riot (nouvel onglet)', $crawler->filter('.work-link')->attr('aria-label'));
    }

    public function testTheServicesAreDescribedForSearchEngines(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);
        self::service('Mix', ServiceKind::Mix, Pricing::Fixed, 12000, 'Stems in, mix out.');

        $crawler = $client->request('GET', '/production');

        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertCount(3, $graph);
        self::assertSame([
            '@type' => 'Service',
            '@id' => 'http://localhost/production#service',
            'name' => 'Music production',
            'serviceType' => 'Music production',
            'url' => 'http://localhost/production',
            'provider' => ['@id' => 'http://localhost/#band'],
            'offers' => [[
                '@type' => 'Offer',
                'name' => 'Mix',
                'description' => 'Stems in, mix out.',
                'url' => 'http://localhost/production',
                'price' => '120.00',
                'priceCurrency' => 'EUR',
            ]],
        ], $graph[2]);
        self::assertSame('Production | Injust', $crawler->filter('title')->text());
    }

    public function testAnAdminSeesTheSwitchedOffPageWithABanner(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/production');

        self::assertResponseIsSuccessful();
        self::assertSame('Hidden from visitors', $crawler->filter('.hidden-banner strong')->text());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }

    public function testTheProductionRouteNoLongerShowsThePlaceholder(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, true);

        $crawler = $client->request('GET', '/fr/production');

        self::assertCount(0, $crawler->filter('.placeholder'));
        self::assertSame(['/fr/production', '/fr/beats'], $crawler->filter('header nav a.nav-section')->each(static fn (Crawler $link): ?string => $link->attr('href')));
        self::assertSame('page', $crawler->filter('header nav a.nav-section')->attr('aria-current'));
        self::assertCount(1, $client->request('GET', '/fr/beats')->filter('.placeholder'));
    }

    public function testTheSitemapListsTheProductionPageWhenTheSectionIsOn(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, false);

        $client->request('GET', '/sitemap.xml');
        $locs = [];
        foreach (new \SimpleXMLElement((string) $client->getResponse()->getContent())->url as $url) {
            $locs[] = (string) $url->loc;
        }

        self::assertSame(['http://localhost/', 'http://localhost/fr', 'http://localhost/production', 'http://localhost/fr/production', 'http://localhost/contact', 'http://localhost/fr/contact'], $locs);
    }

    /** @return list<string> */
    private static function texts(Crawler $crawler, string $selector): array
    {
        return $crawler->filter($selector)->each(static fn (Crawler $node): string => $node->text());
    }

    private static function service(string $title, ServiceKind $kind, Pricing $pricing, int $cents, string $description = '', ?int $deliveryDays = null, ?int $revisions = null, bool $en = false): string
    {
        return self::getContainer()->get(AddProductionServiceHandler::class)(new AddProductionService(new ProductionServiceDetails(
            $en ? new LocalizedText('', $title) : new LocalizedText($title, $title),
            new LocalizedText($description, $description),
            $kind,
            $pricing,
            new Price($cents),
            $deliveryDays,
            $revisions,
        )))->id;
    }

    /** @param list<ServiceKind> $roles */
    private static function work(string $title, string $artist, array $roles, ?int $year, ?string $url): string
    {
        return self::getContainer()->get(AddPortfolioItemHandler::class)(new AddPortfolioItem(new PortfolioItemDetails($title, $artist, $roles, $year, $url)))->id;
    }

    private static function text(string $intro, string $process, string $faq, bool $en = false): void
    {
        $text = static fn (string $value): LocalizedText => $en ? new LocalizedText('', $value) : new LocalizedText($value, $value);
        self::getContainer()->get(EditProductionPageHandler::class)(new EditProductionPage($text($intro), $text($process), $text($faq)));
    }
}
