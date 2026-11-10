<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Settings\LegalKind;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoTest extends WebTestCase
{
    use ConfiguresSite;
    use SignsInClient;

    public function testWhileIndexingIsOffRobotsKeepEveryCrawlerOut(): void
    {
        $client = self::createClient();

        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertSame("User-agent: *\nDisallow: /\n", $client->getResponse()->getContent());
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');
    }

    public function testOnceIndexingIsOnRobotsOpenThePublicSite(): void
    {
        $client = self::createClient();
        self::editGeneral(searchIndexing: true);

        $client->request('GET', '/robots.txt');

        self::assertSame("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\nDisallow: /login\nDisallow: /logout\n\nSitemap: http://localhost/sitemap.xml\n", $client->getResponse()->getContent());
    }

    public function testWhileIndexingIsOffEveryPageIsNoindex(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
    }

    public function testOnceIndexingIsOnPublicPagesAreIndexableButNotTheAdmin(): void
    {
        $client = self::signedInClient();
        self::editGeneral(searchIndexing: true);

        $crawler = $client->request('GET', '/fr');
        self::assertResponseHeaderNotSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('index, follow, max-image-preview:large', $crawler->filter('meta[name="robots"]')->attr('content'));

        foreach (['/admin/', '/api/admin/settings'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function testTheSitemapListsBothLanguagesOfTheVisiblePages(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::writeLegal(LegalKind::Mentions, 'Mentions');
        self::writeLegal(LegalKind::CgvLessons, 'CGV cours');
        self::writeLegal(LegalKind::Licence, 'Licence');

        $client->request('GET', '/sitemap.xml');

        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        self::assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');
        $sitemap = new \SimpleXMLElement((string) $client->getResponse()->getContent());
        $locs = [];
        foreach ($sitemap->url as $url) {
            $locs[] = (string) $url->loc;
        }
        self::assertSame([
            'http://localhost/', 'http://localhost/fr',
            'http://localhost/beats', 'http://localhost/fr/beats',
            'http://localhost/legal/mentions', 'http://localhost/fr/legal/mentions',
            'http://localhost/legal/licence', 'http://localhost/fr/legal/licence',
        ], $locs);
        $links = $sitemap->url[3]->children('http://www.w3.org/1999/xhtml')->link;
        self::assertSame(['en', 'fr'], [(string) $links[0]?->attributes()?->hreflang, (string) $links[1]?->attributes()?->hreflang]);
        self::assertSame(['http://localhost/beats', 'http://localhost/fr/beats'], [(string) $links[0]?->attributes()?->href, (string) $links[1]?->attributes()?->href]);
    }

    public function testPagesCarryAStrictContentSecurityPolicy(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        $policy = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $policy);
        self::assertStringContainsString("script-src 'self';", $policy);
        self::assertStringNotContainsString('frame-src', $policy);
        self::assertStringContainsString("frame-ancestors 'none'", $policy);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }
}
