<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\Go;

use App\Application\Links\AddBioLink\AddBioLink;
use App\Application\Links\AddBioLink\AddBioLinkHandler;
use App\Application\Links\LinksQueries;
use App\Domain\Shared\LocalizedText;
use App\Tests\Functional\Web\ConfiguresSite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class LinkClickTest extends WebTestCase
{
    use ConfiguresSite;

    public function testAClickIsCountedThenRedirectedToTheStoredUrl(): void
    {
        $client = self::createClient();
        $id = self::link('https://open.spotify.com/artist/injust?si=a&b=%2F');

        $client->request('GET', '/go/'.$id);

        self::assertResponseStatusCodeSame(302);
        self::assertSame('https://open.spotify.com/artist/injust?si=a&b=%2F', $client->getResponse()->headers->get('Location'));
        self::assertSame(1, self::clicks($id));
    }

    public function testEachRequestCountsOnceEvenFromSeparateKernels(): void
    {
        $client = self::createClient();
        $id = self::link();

        $client->request('GET', '/go/'.$id);
        $client->request('GET', '/go/'.$id);
        $client->request('GET', '/go/'.$id);

        self::assertSame(3, self::clicks($id));
    }

    public function testAHeadRequestShowsTheTargetWithoutCountingAClick(): void
    {
        $client = self::createClient();
        $id = self::link('https://injust.test/merch');
        self::link('https://injust.test/other');

        $client->request('HEAD', '/go/'.$id);

        self::assertResponseStatusCodeSame(302);
        self::assertSame('https://injust.test/merch', $client->getResponse()->headers->get('Location'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame(0, self::clicks($id));
    }

    public function testAHeadRequestOnAHiddenOrUnknownLinkIsNotFound(): void
    {
        $client = self::createClient();
        $id = self::link(visible: false);

        $client->request('HEAD', '/go/'.$id);
        self::assertResponseStatusCodeSame(404);
        $client->request('HEAD', '/go/'.new Ulid()->toBase32());
        self::assertResponseStatusCodeSame(404);
    }

    public function testAClickNeitherStartsASessionNorSetsACookieAndIsNotIndexed(): void
    {
        $client = self::createClient();
        self::editGeneral(searchIndexing: true);
        $id = self::link();

        $client->request('GET', '/go/'.$id);

        self::assertSame([], $client->getResponse()->headers->getCookies());
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testAHiddenLinkIsNeitherCountedNorFollowed(): void
    {
        $client = self::createClient();
        $id = self::link(visible: false);

        $client->request('GET', '/go/'.$id);

        self::assertResponseStatusCodeSame(404);
        self::assertNull($client->getResponse()->headers->get('Location'));
        self::assertSame(0, self::clicks($id));
    }

    public function testAnUnknownOrMalformedIdIsNotFound(): void
    {
        $client = self::createClient();

        self::assertNotFound($client, '/go/'.new Ulid()->toBase32());
        self::assertNotFound($client, '/go/https:%2F%2Fevil.test');
        self::assertNotFound($client, '/go/evil.test');
        self::assertNotFound($client, '/go/');
    }

    private static function assertNotFound(KernelBrowser $client, string $url): void
    {
        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
        self::assertNull($client->getResponse()->headers->get('Location'));
    }

    private static function link(string $url = 'https://injust.test/merch', bool $visible = true): string
    {
        return self::getContainer()->get(AddBioLinkHandler::class)(new AddBioLink(new LocalizedText('Merch', 'Merch'), $url, null, false, $visible))->id;
    }

    private static function clicks(string $id): int
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        foreach (self::getContainer()->get(LinksQueries::class)->links() as $link) {
            if ($link->id === $id) {
                return $link->clicks;
            }
        }
        self::fail('No link '.$id);
    }
}
