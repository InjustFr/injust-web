<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\Listen;

use App\Application\Music\HideReleasePlatforms\HideReleasePlatforms;
use App\Application\Music\HideReleasePlatforms\HideReleasePlatformsHandler;
use App\Application\Music\MusicQueries;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Tests\Functional\Web\ConfiguresSite;
use App\Tests\Support\FakePlatforms;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class ListenClickTest extends WebTestCase
{
    use ConfiguresSite;
    use ListensToReleases;

    public function testAClickIsCountedThenRedirectedToTheStoredUrl(): void
    {
        $client = self::createClient();
        self::refresh(self::release(upc: FakePlatforms::EVERYWHERE));

        $client->request('GET', '/listen/white-men-are-the-issue/deezer');

        self::assertResponseStatusCodeSame(302);
        self::assertSame('https://www.deezer.com/album/1061026552', $client->getResponse()->headers->get('Location'));
        self::assertSame(['deezer' => 1], self::clicks());
    }

    public function testEachClickCountsOnceAndPlatformsAreCountedApart(): void
    {
        $client = self::createClient();
        self::release(links: [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/x'), new PlatformLink(Platform::Other, 'https://injust.test/one')]);

        $client->request('GET', '/listen/white-men-are-the-issue/bandcamp');
        $client->request('GET', '/listen/white-men-are-the-issue/other');
        self::assertSame('https://injust.test/one', $client->getResponse()->headers->get('Location'));
        $client->request('GET', '/listen/white-men-are-the-issue/bandcamp');

        self::assertSame(['bandcamp' => 2, 'other' => 1], self::clicks());
    }

    public function testAHeadRequestShowsTheTargetWithoutCounting(): void
    {
        $client = self::createClient();
        self::release(links: [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/x')]);

        $client->request('HEAD', '/listen/white-men-are-the-issue/bandcamp');

        self::assertResponseStatusCodeSame(302);
        self::assertSame('https://injust.bandcamp.com/track/x', $client->getResponse()->headers->get('Location'));
        self::assertSame([], self::clicks());
        $client->request('HEAD', '/listen/white-men-are-the-issue/deezer');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAClickNeitherStartsASessionNorSetsACookieAndIsNotIndexed(): void
    {
        $client = self::createClient();
        self::editGeneral(searchIndexing: true);
        self::release(links: [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/x')]);

        $client->request('GET', '/listen/white-men-are-the-issue/bandcamp');

        self::assertSame([], $client->getResponse()->headers->getCookies());
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testAPlatformWithoutVisibleLinkOrAnUnknownReleaseIsNotFound(): void
    {
        $client = self::createClient();
        $id = self::release(upc: FakePlatforms::EVERYWHERE);
        self::refresh($id);
        self::getContainer()->get(HideReleasePlatformsHandler::class)(new HideReleasePlatforms(Ulid::fromString($id), [Platform::Deezer]));

        foreach (['/listen/white-men-are-the-issue/deezer', '/listen/white-men-are-the-issue/bandcamp', '/listen/unknown/deezer', '/listen/white-men-are-the-issue/napster', '/fr/listen/white-men-are-the-issue/spotify'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
            self::assertNull($client->getResponse()->headers->get('Location'));
        }
        self::assertSame([], self::clicks());
    }

    /** @return array<string, int> */
    private static function clicks(): array
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(MusicQueries::class)->releases(new \DateTimeImmutable())[0]->clicks;
    }
}
