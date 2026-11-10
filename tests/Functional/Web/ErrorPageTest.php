<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Settings\LegalKind;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ErrorPageTest extends WebTestCase
{
    use ConfiguresSite;

    public function testAMissingPageKeepsTheSiteLayoutAndIsNeverIndexed(): void
    {
        $client = self::createClient(['debug' => false]);
        self::editGeneral(searchIndexing: true);
        self::writeLegal(LegalKind::Mentions, 'Mentions', 'Legal notice');

        $crawler = $client->request('GET', '/nowhere?x=1');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('en', $crawler->filter('html')->attr('lang'));
        self::assertSame('Nothing here | Injust', $crawler->filter('title')->text());
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertCount(0, $crawler->filter('link[rel="canonical"], link[rel="alternate"]'));
        self::assertSame('404', $crawler->filter('main .placeholder-status')->text());
        self::assertSame('Nothing here', $crawler->filter('main h1')->text());
        self::assertSame('/', $crawler->filter('main a.button')->attr('href'));
        self::assertSame('INJUST', $crawler->filter('header .wordmark')->text());
        self::assertSame('/fr', $crawler->filter('header a.lang-switch')->attr('href'));
        self::assertSame(['/legal/mentions'], $crawler->filter('footer a.legal-link')->each(static fn (Crawler $a): string => (string) $a->attr('href')));
    }

    public function testAMissingFrenchPageAnswersInFrench(): void
    {
        $client = self::createClient(['debug' => false]);

        $crawler = $client->request('GET', '/fr/nulle-part', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('Rien ici', $crawler->filter('main h1')->text());
        self::assertSame('/fr', $crawler->filter('main a.button')->attr('href'));
        self::assertSame('/', $crawler->filter('header a.lang-switch')->attr('href'));
    }

    public function testAPageThatRefusesToShowItselfUsesTheSameLayout(): void
    {
        $client = self::createClient(['debug' => false]);

        $crawler = $client->request('GET', '/fr/lessons');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('Rien ici | Injust', $crawler->filter('title')->text());
        self::assertCount(1, $crawler->filter('header .site-nav'));
    }

    public function testAMissingLinkKeepsTheLanguageTheBrowserAskedFor(): void
    {
        $client = self::createClient(['debug' => false]);

        $crawler = $client->request('GET', '/go/01JC0000000000000000000000', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('/fr', $crawler->filter('main a.button')->attr('href'));
    }

    public function testAnyOtherErrorStaysBrandedAndNoindex(): void
    {
        $client = self::createClient(['debug' => false]);

        $crawler = $client->request('DELETE', '/fr');

        self::assertResponseStatusCodeSame(405);
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('405', $crawler->filter('main .placeholder-status')->text());
        self::assertSame('/fr', $crawler->filter('header .wordmark')->attr('href'));
        self::assertNotSame('', $crawler->filter('main h1')->text());
    }
}
