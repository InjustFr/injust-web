<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Music;

use App\Tests\Support\FakePlatforms;
use App\Tests\Support\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class ReleaseLinksApiTest extends WebTestCase
{
    use CallsMusicApi;

    private const array RELEASE = [
        'title' => 'White Men are the Issue',
        'type' => 'single',
        'releaseDate' => '2026-06-01',
        'links' => [['platform' => 'bandcamp', 'url' => 'https://injust.bandcamp.com/track/white-men']],
        'upc' => FakePlatforms::EVERYWHERE,
    ];

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00 UTC'));
    }

    public function testTheUpcIsSavedWithTheRelease(): void
    {
        $client = self::signedInClient();

        $release = self::send($client, 'POST', '/api/admin/music/releases', ['upc' => ' '.FakePlatforms::EVERYWHERE.' '] + self::RELEASE);
        self::assertSame(FakePlatforms::EVERYWHERE, Json::at($release, 'upc'));

        $edited = self::send($client, 'PUT', '/api/admin/music/releases/'.Json::string($release, 'id'), ['upc' => ''] + self::RELEASE);
        self::assertNull(Json::at($edited, 'upc'));
    }

    public function testAnInvalidUpcIsExplained(): void
    {
        $detail = self::refused(self::signedInClient(), 'POST', '/api/admin/music/releases', ['upc' => '701518909441'] + self::RELEASE);

        self::assertSame('“701518909441” is not a valid UPC or EAN: type the 12 or 13 digits printed under the barcode.', $detail);
    }

    public function testTwoReleasesCannotShareAUpc(): void
    {
        $client = self::signedInClient();
        self::send($client, 'POST', '/api/admin/music/releases', self::RELEASE);
        $other = self::send($client, 'POST', '/api/admin/music/releases', ['title' => 'Other', 'upc' => null] + self::RELEASE);

        self::assertSame('Another release already uses the UPC 701518909440.', self::refused($client, 'POST', '/api/admin/music/releases', ['title' => 'Third'] + self::RELEASE));
        self::assertSame('Another release already uses the UPC 701518909440.', self::refused($client, 'PUT', '/api/admin/music/releases/'.Json::string($other, 'id'), ['title' => 'Other'] + self::RELEASE));
        self::send($client, 'PUT', '/api/admin/music/releases/'.Json::string($other, 'id'), ['title' => 'Other', 'upc' => FakePlatforms::PARTIAL] + self::RELEASE);
    }

    public function testARefreshFindsTheReleaseOnEveryPlatform(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::send($client, 'POST', '/api/admin/music/releases', self::RELEASE), 'id');

        $answer = self::send($client, 'POST', '/api/admin/music/releases/'.$id.'/links/refresh');

        self::assertSame(['spotify' => 'found', 'apple-music' => 'found', 'deezer' => 'found', 'tidal' => 'found'], Json::array($answer, 'report'));
        self::assertSame([
            ['platform' => 'bandcamp', 'url' => 'https://injust.bandcamp.com/track/white-men', 'source' => 'manual'],
            ['platform' => 'spotify', 'url' => 'https://open.spotify.com/album/'.FakePlatforms::SPOTIFY_ID, 'source' => 'auto'],
            ['platform' => 'apple-music', 'url' => 'https://music.apple.com/album/6804428384', 'source' => 'auto'],
            ['platform' => 'deezer', 'url' => 'https://www.deezer.com/album/1061026552', 'source' => 'auto'],
            ['platform' => 'tidal', 'url' => 'https://tidal.com/album/'.FakePlatforms::TIDAL_ID, 'source' => 'auto'],
        ], Json::array($answer, 'release', 'links'));
        self::assertSame('2026-11-10T09:00:00+00:00', Json::string($answer, 'release', 'linksCheckedAt'));
        self::assertSame('http://localhost/listen/white-men-are-the-issue', Json::string($answer, 'release', 'listenUrl'));
    }

    public function testFailuresKeepThePreviousLinksAndAreReported(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::send($client, 'POST', '/api/admin/music/releases', ['upc' => FakePlatforms::FAILING] + self::RELEASE), 'id');

        $answer = self::send($client, 'POST', '/api/admin/music/releases/'.$id.'/links/refresh');

        self::assertSame(['spotify' => 'failed', 'apple-music' => 'notFound', 'deezer' => 'failed', 'tidal' => 'notFound'], Json::array($answer, 'report'));
        self::assertCount(1, Json::array($answer, 'release', 'links'));
    }

    public function testAReleaseWithoutUpcIsNotRefreshed(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::send($client, 'POST', '/api/admin/music/releases', ['upc' => null] + self::RELEASE), 'id');

        self::assertSame('Set the UPC of this release first: the links are found from it.', self::refused($client, 'POST', '/api/admin/music/releases/'.$id.'/links/refresh'));
        self::refused($client, 'POST', '/api/admin/music/releases/'.new Ulid()->toBase32().'/links/refresh', status: 404);
    }

    public function testHiddenPlatformsLoseTheirAutoLinkUntilShownAgain(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::send($client, 'POST', '/api/admin/music/releases', self::RELEASE), 'id');
        self::send($client, 'POST', '/api/admin/music/releases/'.$id.'/links/refresh');

        $hidden = self::send($client, 'PUT', '/api/admin/music/releases/'.$id.'/hidden-platforms', ['platforms' => ['tidal', 'deezer']]);
        self::assertSame(['tidal', 'deezer'], Json::array($hidden, 'hiddenPlatforms'));
        self::assertSame(['bandcamp', 'spotify', 'apple-music'], array_column(Json::array($hidden, 'links'), 'platform'));

        $refreshed = self::send($client, 'POST', '/api/admin/music/releases/'.$id.'/links/refresh');
        self::assertSame(['bandcamp', 'spotify', 'apple-music'], array_column(Json::array($refreshed, 'release', 'links'), 'platform'));

        self::send($client, 'PUT', '/api/admin/music/releases/'.$id.'/hidden-platforms', ['platforms' => []]);
        $shown = self::send($client, 'POST', '/api/admin/music/releases/'.$id.'/links/refresh');
        self::assertSame(['bandcamp', 'spotify', 'apple-music', 'deezer', 'tidal'], array_column(Json::array($shown, 'release', 'links'), 'platform'));
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidHiddenPlatforms(): iterable
    {
        yield 'unknown platform' => [['platforms' => ['napster']]];
        yield 'not a list' => [['platforms' => 'deezer']];
    }

    /** @param array<mixed> $body */
    #[DataProvider('invalidHiddenPlatforms')]
    public function testInvalidHiddenPlatformsAreRefused(array $body): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::send($client, 'POST', '/api/admin/music/releases', self::RELEASE), 'id');

        $client->jsonRequest('PUT', '/api/admin/music/releases/'.$id.'/hidden-platforms', $body);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAManualLinkSentBackAsAutoIsIgnored(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::send($client, 'POST', '/api/admin/music/releases', self::RELEASE), 'id');
        $refreshed = self::send($client, 'POST', '/api/admin/music/releases/'.$id.'/links/refresh');

        $edited = self::send($client, 'PUT', '/api/admin/music/releases/'.$id, ['links' => Json::array($refreshed, 'release', 'links')] + self::RELEASE);

        self::assertSame(Json::array($refreshed, 'release', 'links'), Json::array($edited, 'links'));
    }

    public function testTheAdminSeesWhichFindersAreConfiguredAndTheClicks(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::send($client, 'POST', '/api/admin/music/releases', self::RELEASE), 'id');
        self::send($client, 'POST', '/api/admin/music/releases/'.$id.'/links/refresh');
        $client->request('GET', '/listen/white-men-are-the-issue/deezer');
        $client->request('GET', '/listen/white-men-are-the-issue/deezer');
        $client->request('GET', '/listen/white-men-are-the-issue/bandcamp');

        $client->jsonRequest('GET', '/api/admin/music');
        $content = (string) $client->getResponse()->getContent();
        $music = Json::decode($content);

        self::assertSame([['platform' => 'spotify', 'configured' => true], ['platform' => 'apple-music', 'configured' => true], ['platform' => 'deezer', 'configured' => true], ['platform' => 'tidal', 'configured' => true]], Json::array($music, 'finders'));
        self::assertSame(['bandcamp' => 1, 'deezer' => 2], Json::array($music, 'releases', 0, 'clicks'));
    }

    public function testNoClickYetIsAnEmptyObject(): void
    {
        $client = self::signedInClient();
        self::send($client, 'POST', '/api/admin/music/releases', self::RELEASE);

        self::assertStringContainsString('"clicks":{}', (string) $client->getResponse()->getContent());
    }

    public function testVisitorsCannotRefreshOrHide(): void
    {
        $client = self::createClient();
        $id = new Ulid()->toBase32();

        $client->jsonRequest('POST', '/api/admin/music/releases/'.$id.'/links/refresh');
        self::assertResponseStatusCodeSame(401);
        $client->jsonRequest('PUT', '/api/admin/music/releases/'.$id.'/hidden-platforms', ['platforms' => []]);
        self::assertResponseStatusCodeSame(401);
    }
}
