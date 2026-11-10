<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitePagesTest extends WebTestCase
{
    public function testTheHomePageNeedsNoSession(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('INJUST', $crawler->filter('#site h1')->text());
        self::assertSame('en', $crawler->filter('html')->attr('lang'));
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
    }

    public function testTheHomePageDeclaresItselfToSearchEngines(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertSelectorTextSame('title', 'Injust');
        self::assertSame('http://localhost/', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('index, follow, max-image-preview:large', $crawler->filter('meta[name="robots"]')->attr('content'));
        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertSame('MusicGroup', Json::string($graph, 0, '@type'));
        self::assertSame('WebSite', Json::string($graph, 1, '@type'));
    }

    public function testRobotsKeepCrawlersOnThePublicSite(): void
    {
        $client = self::createClient();

        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        $robots = (string) $client->getResponse()->getContent();
        self::assertStringContainsString("Disallow: /admin\nDisallow: /api/\n", $robots);
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $robots);
    }

    public function testTheSitemapListsTheHomePage(): void
    {
        $client = self::createClient();

        $client->request('GET', '/sitemap.xml');

        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $sitemap = new \SimpleXMLElement((string) $client->getResponse()->getContent());
        self::assertSame('http://localhost/', (string) $sitemap->url[0]->loc);
    }
}
