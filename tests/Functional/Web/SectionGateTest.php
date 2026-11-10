<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SectionGateTest extends WebTestCase
{
    use ConfiguresSite;
    use SignsInClient;

    public function testASwitchedOffSectionIsNotFoundForVisitors(): void
    {
        $client = self::createClient();

        foreach (['/lessons', '/production', '/beats', '/fr/lessons', '/fr/production', '/fr/beats'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
    }

    public function testASwitchedOnSectionIsPublic(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);

        $crawler = $client->request('GET', '/lessons');

        self::assertResponseIsSuccessful();
        self::assertSame('Lessons', $crawler->filter('main h1')->text());
        self::assertCount(0, $crawler->filter('.hidden-banner'));
        self::assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, public');
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
    }

    public function testAnAdminSeesASwitchedOffSectionWithABanner(): void
    {
        $client = self::signedInClient();
        self::editGeneral(searchIndexing: true);

        $crawler = $client->request('GET', '/fr/beats');

        self::assertResponseIsSuccessful();
        self::assertSame('Beats', $crawler->filter('main h1')->text());
        self::assertSame('Section masquée aux visiteurs', $crawler->filter('.hidden-banner strong')->text());
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');
    }

    public function testAnUnknownSectionIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/merch');

        self::assertResponseStatusCodeSame(404);
    }
}
