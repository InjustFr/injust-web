<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Music;

use App\Application\Music\MusicQueries;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class ReleasesApiTest extends WebTestCase
{
    use CallsMusicApi;

    private const string SPOTIFY_ID = '4aawyAB9vmqN3uQ7FjRGTy';

    private const array RELEASE = [
        'title' => 'No Gods, No Masters',
        'type' => 'album',
        'releaseDate' => '2026-12-01',
        'description' => ['fr' => 'Premier album', 'en' => 'First album'],
        'tracks' => [['title' => 'Intro', 'durationSeconds' => 61], ['title' => 'Riot']],
        'links' => [['platform' => 'bandcamp', 'url' => 'https://injust.bandcamp.com'], ['platform' => 'apple-music', 'url' => 'https://music.apple.com/album/1']],
        'smartLinkUrl' => 'https://ffm.to/nogods',
        'spotifyUrl' => 'https://open.spotify.com/album/'.self::SPOTIFY_ID.'?si=x',
    ];

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00'));
    }

    public function testAReleaseIsAddedWithEveryDetail(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/music/releases', self::RELEASE);

        self::assertResponseStatusCodeSame(201);
        $release = Json::decode((string) $client->getResponse()->getContent());
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', Json::string($release, 'id'));
        self::assertSame([
            'title' => 'No Gods, No Masters',
            'slug' => 'no-gods-no-masters',
            'type' => 'album',
            'releaseDate' => '2026-12-01',
            'releaseAt' => '2026-12-01T00:00:00+01:00',
            'upcoming' => true,
            'cover' => null,
            'description' => ['fr' => 'Premier album', 'en' => 'First album'],
            'tracks' => [['position' => 1, 'title' => 'Intro', 'durationSeconds' => 61], ['position' => 2, 'title' => 'Riot', 'durationSeconds' => null]],
            'links' => [['platform' => 'bandcamp', 'url' => 'https://injust.bandcamp.com', 'source' => 'manual'], ['platform' => 'apple-music', 'url' => 'https://music.apple.com/album/1', 'source' => 'manual']],
            'smartLinkUrl' => 'https://ffm.to/nogods',
            'spotifyEmbed' => ['kind' => 'album', 'id' => self::SPOTIFY_ID, 'url' => 'https://open.spotify.com/album/'.self::SPOTIFY_ID, 'embedUrl' => 'https://open.spotify.com/embed/album/'.self::SPOTIFY_ID],
            'featured' => false,
            'position' => 0,
            'upc' => null,
            'linksCheckedAt' => null,
            'hiddenPlatforms' => [],
            'clicks' => [],
            'listenUrl' => 'http://localhost/listen/no-gods-no-masters',
        ], array_diff_key($release, ['id' => true]));
    }

    public function testTheOptionalFieldsCanBeLeftOut(): void
    {
        $client = self::signedInClient();

        $release = self::send($client, 'POST', '/api/admin/music/releases', ['title' => 'Riot', 'type' => 'single', 'releaseDate' => '2026-01-01']);

        self::assertSame(['riot', false, ['fr' => '', 'en' => ''], [], [], null, null], [Json::string($release, 'slug'), Json::at($release, 'upcoming'), Json::array($release, 'description'), Json::array($release, 'tracks'), Json::array($release, 'links'), Json::at($release, 'smartLinkUrl'), Json::at($release, 'spotifyEmbed')]);
    }

    public function testASmartLinkFillsItsWholeColumn(): void
    {
        $url = 'https://ffm.to/'.str_repeat('a', 240);

        self::assertSame($url, Json::string(self::send(self::signedInClient(), 'POST', '/api/admin/music/releases', ['smartLinkUrl' => $url] + self::RELEASE), 'smartLinkUrl'));
    }

    public function testEachNewReleaseGoesLast(): void
    {
        $client = self::signedInClient();
        self::add($client, 'One');

        self::assertSame(1, Json::int(self::add($client, 'Two'), 'position'));
    }

    public function testAReleaseIsEdited(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $release = self::send($client, 'PUT', '/api/admin/music/releases/'.$id, ['title' => 'Riot', 'slug' => 'riot-ep', 'type' => 'ep', 'releaseDate' => '2025-05-05', 'tracks' => [['title' => 'Riot', 'durationSeconds' => 200]], 'spotifyUrl' => ' ', 'smartLinkUrl' => '']);

        self::assertSame([$id, 'Riot', 'riot-ep', 'ep', '2025-05-05', false, null, null], [Json::string($release, 'id'), Json::string($release, 'title'), Json::string($release, 'slug'), Json::string($release, 'type'), Json::string($release, 'releaseDate'), Json::at($release, 'upcoming'), Json::at($release, 'spotifyEmbed'), Json::at($release, 'smartLinkUrl')]);
        self::assertSame([['position' => 1, 'title' => 'Riot', 'durationSeconds' => 200]], Json::array($release, 'tracks'));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame('riot-ep', self::getContainer()->get(MusicQueries::class)->releases(new \DateTimeImmutable())[0]->slug);
    }

    public function testAReleaseKeepsItsOwnSlugWhenEdited(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        self::send($client, 'PUT', '/api/admin/music/releases/'.$id, self::RELEASE);
    }

    public function testTwoReleasesCannotShareASlug(): void
    {
        $client = self::signedInClient();
        self::add($client);
        $other = Json::string(self::add($client, 'Riot'), 'id');

        self::assertStringContainsString('no-gods-no-masters', self::refused($client, 'POST', '/api/admin/music/releases', self::RELEASE));
        self::refused($client, 'PUT', '/api/admin/music/releases/'.$other, ['slug' => 'No Gods No Masters'] + self::RELEASE);
    }

    /** @return iterable<array{array<string, mixed>}> */
    public static function invalidReleases(): iterable
    {
        yield 'blank title' => [['title' => ' '] + self::RELEASE];
        yield 'title too long' => [['title' => str_repeat('a', 201)] + self::RELEASE];
        yield 'unknown type' => [['type' => 'mixtape'] + self::RELEASE];
        yield 'bad date' => [['releaseDate' => '2026-13-01'] + self::RELEASE];
        yield 'no date' => [array_diff_key(self::RELEASE, ['releaseDate' => true])];
        yield 'blank track title' => [['tracks' => [['title' => '']]] + self::RELEASE];
        yield 'track title too long' => [['tracks' => [['title' => str_repeat('a', 201)]]] + self::RELEASE];
        yield 'zero duration' => [['tracks' => [['title' => 'Riot', 'durationSeconds' => 0]]] + self::RELEASE];
        yield 'unknown platform' => [['links' => [['platform' => 'napster', 'url' => 'https://napster.test']]] + self::RELEASE];
        yield 'insecure link' => [['links' => [['platform' => 'deezer', 'url' => 'http://deezer.test']]] + self::RELEASE];
        yield 'insecure smart link' => [['smartLinkUrl' => 'http://ffm.to/x'] + self::RELEASE];
        yield 'smart link too long' => [['smartLinkUrl' => 'https://ffm.to/'.str_repeat('a', 241)] + self::RELEASE];
        yield 'not a spotify link' => [['spotifyUrl' => 'https://open.spotify.com/artist/'.self::SPOTIFY_ID] + self::RELEASE];
        yield 'slug without letters' => [['slug' => '!!!'] + self::RELEASE];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('invalidReleases')]
    public function testAnInvalidReleaseIsRefused(array $body): void
    {
        $client = self::signedInClient();

        self::refused($client, 'POST', '/api/admin/music/releases', $body);

        self::assertSame([], self::getContainer()->get(MusicQueries::class)->releases(new \DateTimeImmutable()));
    }

    public function testTheSpotifyErrorIsExplained(): void
    {
        $client = self::signedInClient();

        self::assertStringContainsString('Spotify', self::refused($client, 'POST', '/api/admin/music/releases', ['spotifyUrl' => 'https://evil.test'] + self::RELEASE));
    }

    public function testAnUnknownReleaseIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/music/releases/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, self::RELEASE, 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
        self::refused($client, 'PUT', $unknown.'/featured', ['featured' => true], 404);
        self::refused($client, 'DELETE', $unknown.'/cover', status: 404);
        self::assertSame(404, Json::int(self::upload($client, $unknown.'/cover'), 'status'));
    }

    public function testOnlyOneReleaseIsFeatured(): void
    {
        $client = self::signedInClient();
        $first = Json::string(self::add($client, 'One'), 'id');
        $second = Json::string(self::add($client, 'Two'), 'id');

        self::assertTrue(Json::at(self::send($client, 'PUT', '/api/admin/music/releases/'.$first.'/featured', ['featured' => true]), 'featured'));
        self::assertTrue(Json::at(self::send($client, 'PUT', '/api/admin/music/releases/'.$second.'/featured', ['featured' => true]), 'featured'));

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $home = self::getContainer()->get(MusicQueries::class)->home(new \DateTimeImmutable('2026-11-10'));
        self::assertSame($second, $home->featured?->id);
        self::assertSame([false, true], array_map(static fn ($release): bool => $release->featured, $home->releases));

        self::assertFalse(Json::at(self::send($client, 'PUT', '/api/admin/music/releases/'.$second.'/featured', ['featured' => false]), 'featured'));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(MusicQueries::class)->home(new \DateTimeImmutable())->featured);
    }

    public function testFeaturingWithoutAFlagUnfeatures(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');
        self::send($client, 'PUT', '/api/admin/music/releases/'.$id.'/featured', ['featured' => true]);

        self::assertFalse(Json::at(self::send($client, 'PUT', '/api/admin/music/releases/'.$id.'/featured'), 'featured'));
    }

    public function testTheHomeSaysWhetherEachReleaseIsUpcoming(): void
    {
        $client = self::signedInClient();
        self::add($client, 'Out', '2026-11-10');
        self::add($client, 'Soon', '2026-11-11');

        $queries = self::getContainer()->get(MusicQueries::class);
        $home = $queries->home(new \DateTimeImmutable('2026-11-10 22:59:59 UTC'));

        self::assertSame([['Out', false, '2026-11-10'], ['Soon', true, '2026-11-11']], array_map(static fn ($release): array => [$release->title, $release->upcoming, $release->releaseDate], $home->releases));
        self::assertNull($home->featured);
        self::assertSame([false, false], array_map(static fn ($release): bool => $release->upcoming, $queries->home(new \DateTimeImmutable('2026-11-10 23:00:00 UTC'))->releases));
    }

    public function testReleasesAreReordered(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client, 'A'), 'id');
        $b = Json::string(self::add($client, 'B'), 'id');
        $c = Json::string(self::add($client, 'C'), 'id');

        $releases = self::send($client, 'PUT', '/api/admin/music/releases/order', ['ids' => [$c, $a, $b]]);

        self::assertSame([[$c, 0], [$a, 1], [$b, 2]], array_map(static fn (mixed $release): array => [Json::string($release, 'id'), Json::int($release, 'position')], $releases));
        self::assertSame(['2026-12-01', true], [Json::string($releases, 0, 'releaseDate'), Json::at($releases, 0, 'upcoming')]);
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/music'), 'releases'), $releases);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(['C', 'A', 'B'], array_map(static fn ($release): string => $release->title, self::getContainer()->get(MusicQueries::class)->releases(new \DateTimeImmutable())));
    }

    public function testAnOrderThatIsNotExactlyTheReleasesIsRefused(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client, 'A'), 'id');
        self::add($client, 'B');

        self::assertStringContainsString('every', self::refused($client, 'PUT', '/api/admin/music/releases/order', ['ids' => [$a]]));
        self::refused($client, 'PUT', '/api/admin/music/releases/order', ['ids' => [$a, 1]]);
    }

    public function testTheCoverIsUploadedReplacedAndRemoved(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $first = Json::string(self::upload($client, '/api/admin/music/releases/'.$id.'/cover'), 'cover');
        $second = Json::string(self::upload($client, '/api/admin/music/releases/'.$id.'/cover'), 'cover');

        self::assertSame([false, true], [self::imageExists($client, $first), self::imageExists($client, $second)]);
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/music/releases/'.$id.'/cover'), 'cover'));
        self::assertFalse(self::imageExists($client, $second));
        self::assertNull(Json::at(self::send($client, 'GET', '/api/admin/music'), 'releases', 0, 'cover'));
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/music/releases/'.$id.'/cover'), 'cover'));
    }

    public function testACoverThatIsNotAnImageIsRefused(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        self::assertSame(422, Json::int(self::upload($client, '/api/admin/music/releases/'.$id.'/cover', 'not-an-image.png'), 'status'));
        $client->request('POST', '/api/admin/music/releases/'.$id.'/cover', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testADeletedReleaseTakesItsCoverWithIt(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');
        $cover = Json::string(self::upload($client, '/api/admin/music/releases/'.$id.'/cover'), 'cover');
        $kept = Json::string(self::add($client, 'Riot'), 'id');

        $client->jsonRequest('DELETE', '/api/admin/music/releases/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertFalse(self::imageExists($client, $cover));
        self::assertSame([$kept], array_map(static fn ($release): string => $release->id, self::getContainer()->get(MusicQueries::class)->releases(new \DateTimeImmutable())));
    }

    public function testADeletedReleaseWithoutCoverIsGone(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        self::send($client, 'DELETE', '/api/admin/music/releases/'.$id);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([], self::getContainer()->get(MusicQueries::class)->releases(new \DateTimeImmutable()));
    }

    public function testTheAdminPayloadHoldsEverything(): void
    {
        $client = self::signedInClient();
        self::add($client);

        $music = self::send($client, 'GET', '/api/admin/music');

        self::assertSame(['releases', 'videos', 'text', 'socialLinks', 'finders'], array_keys($music));
        self::assertSame('no-gods-no-masters', Json::string($music, 'releases', 0, 'slug'));
        self::assertTrue(Json::at($music, 'releases', 0, 'upcoming'));
        self::assertSame([[], []], [Json::array($music, 'videos'), Json::array($music, 'socialLinks')]);
        self::assertSame(['tagline' => ['fr' => '', 'en' => ''], 'manifesto' => ['fr' => '', 'en' => ''], 'bio' => ['fr' => '', 'en' => ''], 'hooks' => [], 'portrait' => null], Json::array($music, 'text'));
    }

    public function testAWriteReturnsTheRefreshedMusic(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/music/releases', self::RELEASE, ['HTTP_X_REFRESH' => '["/api/admin/music"]']);

        self::assertResponseIsSuccessful();
        self::assertSame('no-gods-no-masters', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'refreshed', '/api/admin/music', 'releases', 0, 'slug'));
    }

    public function testVisitorsAreKeptOut(): void
    {
        $client = self::createClient();
        $client->jsonRequest('GET', '/api/admin/music');
        self::assertResponseStatusCodeSame(401);
    }

    public function testOtherOriginsAreKeptOut(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/music/releases', self::RELEASE, ['HTTP_ORIGIN' => 'https://evil.test']);
        self::assertResponseStatusCodeSame(403);
    }

    /** @return array<mixed> */
    private static function add(KernelBrowser $client, string $title = self::RELEASE['title'], string $date = '2026-12-01'): array
    {
        return self::send($client, 'POST', '/api/admin/music/releases', ['title' => $title, 'releaseDate' => $date] + self::RELEASE);
    }
}
