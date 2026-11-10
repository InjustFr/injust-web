<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Lessons\AddLessonOffer\AddLessonOffer;
use App\Application\Lessons\AddLessonOffer\AddLessonOfferHandler;
use App\Application\Lessons\EditLessonsPage\EditLessonsPage;
use App\Application\Lessons\EditLessonsPage\EditLessonsPageHandler;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class LessonsPageTest extends WebTestCase
{
    use ConfiguresSite;
    use SignsInClient;

    public function testTheOffersAreGroupedByFormatWithTheirPriceAndAContactLink(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        $online = self::offer('Online pack', OfferKind::Pack, 4, LessonFormat::Online, 45, 25001, 'Ten weeks of riffs.');
        $home = self::offer('At your place', OfferKind::Single, 1, LessonFormat::AtHome, 60, 5000);
        $mine = self::offer('At mine', OfferKind::Single, 1, LessonFormat::AtTeacher, 90, 4550);
        $homePack = self::offer('Home pack', OfferKind::Pack, 3, LessonFormat::AtHome, 60, 20000);

        $crawler = $client->request('GET', '/lessons');

        self::assertResponseIsSuccessful();
        self::assertSame('Lessons', $crawler->filter('main h1')->text());
        self::assertSame(['At your place', 'At my place', 'Online'], $crawler->filter('.offer-group-title')->each(static fn (Crawler $title): string => $title->text()));
        self::assertSame([['At your place', 'Home pack'], ['At mine'], ['Online pack']], $crawler->filter('.offer-group')->each(static fn (Crawler $group): array => $group->filter('.offer-title')->each(static fn (Crawler $title): string => $title->text())));
        self::assertSame(['1 × 60 min', '3 × 60 min', '1 × 90 min', '4 × 45 min'], $crawler->filter('.offer-meta')->each(static fn (Crawler $meta): string => $meta->text()));
        self::assertSame(['€50', '€200', '€45.50', '€250.01'], $crawler->filter('.offer-price')->each(static fn (Crawler $price): string => $price->text()));
        self::assertSame(['€66.67 per lesson', '€62.50 per lesson'], $crawler->filter('.offer-per-lesson')->each(static fn (Crawler $price): string => $price->text()));
        self::assertSame('Ten weeks of riffs.', $crawler->filter('.offer-description')->text());
        self::assertSame(
            ['/contact?topic=lessons&offer='.$home, '/contact?topic=lessons&offer='.$homePack, '/contact?topic=lessons&offer='.$mine, '/contact?topic=lessons&offer='.$online],
            $crawler->filter('.offer a.button')->each(static fn (Crawler $link): ?string => $link->attr('href')),
        );
        self::assertSame('Contact me', $crawler->filter('.offer a.button')->first()->text());
        self::assertCount(0, $crawler->filter('.offers-empty'));
    }

    public function testTheFrenchPageFormatsPricesAndLinksInFrench(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        $id = self::offer('Pack maison', OfferKind::Pack, 2, LessonFormat::AtHome, 60, 9000, en: 'Home pack');

        $crawler = $client->request('GET', '/fr/lessons');

        self::assertSame('Cours', $crawler->filter('main h1')->text());
        self::assertSame('Pack maison', $crawler->filter('.offer-title')->text());
        self::assertSame("90\u{a0}€", $crawler->filter('.offer-price')->text());
        self::assertSame("45\u{a0}€ par cours", $crawler->filter('.offer-per-lesson')->text());
        self::assertSame('/fr/contact?topic=lessons&offer='.$id, $crawler->filter('.offer a.button')->attr('href'));
    }

    public function testTheIntroAndFaqAreEscapedMarkdown(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        self::text("Play **loud**.\n\n<script>alert(1)</script>", "Some words first.\n\n### Do I need a guitar?\n\nYes.");

        $crawler = $client->request('GET', '/lessons');

        self::assertSame('loud', $crawler->filter('.lessons-intro strong')->text());
        self::assertCount(0, $crawler->filter('main script'));
        self::assertStringContainsString('&lt;script&gt;', $crawler->filter('.lessons-intro')->html());
        self::assertCount(0, $crawler->filter('.faq details'));
        self::assertSame('Do I need a guitar?', $crawler->filter('.faq-body h3')->text());
    }

    public function testAFaqMadeOfQuestionsFoldsEachAnswer(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        self::text('', "\n### Do I need a guitar?\n\nYes, **any** guitar.\n\n### Where?\n\nAt home or online.\n");

        $crawler = $client->request('GET', '/lessons');

        self::assertSame(['Do I need a guitar?', 'Where?'], $crawler->filter('.faq details summary')->each(static fn (Crawler $summary): string => $summary->text()));
        self::assertSame('any', $crawler->filter('.faq details')->first()->filter('.faq-answer strong')->text());
        self::assertSame('At home or online.', $crawler->filter('.faq details')->last()->filter('.faq-answer')->text());
        self::assertCount(0, $crawler->filter('.lessons-intro'));
    }

    public function testWithoutOffersThePageSaysSoAndPointsToTheContactForm(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);

        $crawler = $client->request('GET', '/lessons');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.offer'));
        self::assertCount(0, $crawler->filter('.faq'));
        self::assertSame('/contact?topic=lessons', $crawler->filter('.offers-empty a')->attr('href'));
    }

    public function testTheOffersAreDescribedAsAServiceForSearchEngines(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        self::offer('Single lesson', OfferKind::Single, 1, LessonFormat::Online, 60, 12550, 'One hour.');

        $crawler = $client->request('GET', '/lessons');

        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertCount(3, $graph);
        self::assertSame([
            '@type' => 'Service',
            '@id' => 'http://localhost/lessons#service',
            'name' => 'Guitar lessons',
            'serviceType' => 'Guitar lessons',
            'url' => 'http://localhost/lessons',
            'provider' => ['@id' => 'http://localhost/#band'],
            'offers' => [[
                '@type' => 'Offer',
                'name' => 'Single lesson',
                'description' => 'One hour.',
                'price' => '125.50',
                'priceCurrency' => 'EUR',
                'url' => 'http://localhost/lessons',
            ]],
        ], $graph[2]);
        self::assertSame('Lessons | Injust', $crawler->filter('title')->text());
    }

    public function testAnAdminSeesTheSwitchedOffLessonsPageWithABanner(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/lessons');

        self::assertResponseIsSuccessful();
        self::assertSame('Hidden from visitors', $crawler->filter('.hidden-banner strong')->text());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }

    public function testTheLessonsRouteNoLongerShowsThePlaceholder(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);

        $crawler = $client->request('GET', '/fr/lessons');

        self::assertCount(0, $crawler->filter('.placeholder'));
        self::assertSame('/fr/lessons', $crawler->filter('header nav a.nav-section')->attr('href'));
        self::assertSame('page', $crawler->filter('header nav a.nav-section')->attr('aria-current'));
    }

    public function testTheSitemapListsTheLessonsOnlyWhenTheSectionIsOn(): void
    {
        $client = self::createClient();

        self::assertSame(['http://localhost/', 'http://localhost/fr', 'http://localhost/contact', 'http://localhost/fr/contact'], self::sitemap($client));

        self::switchSections(true, true, false);
        self::assertSame([
            'http://localhost/', 'http://localhost/fr',
            'http://localhost/lessons', 'http://localhost/fr/lessons',
            'http://localhost/production', 'http://localhost/fr/production',
            'http://localhost/contact', 'http://localhost/fr/contact',
        ], self::sitemap($client));
    }

    /** @return list<string> */
    private static function sitemap(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        $client->request('GET', '/sitemap.xml');
        $locs = [];
        foreach (new \SimpleXMLElement((string) $client->getResponse()->getContent())->url as $url) {
            $locs[] = (string) $url->loc;
        }

        return $locs;
    }

    private static function offer(string $title, OfferKind $kind, int $sessions, LessonFormat $format, int $minutes, int $cents, string $description = '', string $en = ''): string
    {
        return self::getContainer()->get(AddLessonOfferHandler::class)(new AddLessonOffer(new LessonOfferDetails(
            new LocalizedText($title, $en),
            new LocalizedText($description),
            $kind,
            $sessions,
            $format,
            $minutes,
            new Price($cents),
        )))->id;
    }

    private static function text(string $intro, string $faq): void
    {
        self::getContainer()->get(EditLessonsPageHandler::class)(new EditLessonsPage(new LocalizedText($intro), new LocalizedText($faq)));
    }
}
