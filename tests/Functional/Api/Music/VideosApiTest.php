<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Music;

use App\Application\Music\MusicQueries;
use App\Tests\Support\FakeYoutube;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class VideosApiTest extends WebTestCase
{
    use CallsMusicApi;

    public function testAVideoIsAddedFromItsLinkWithASelfHostedThumbnail(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/music/videos', ['url' => 'https://youtu.be/dQw4w9WgXcQ?si=x', 'title' => ' Riot (official video) ']);

        self::assertResponseStatusCodeSame(201);
        $video = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([
            'youtubeId' => 'dQw4w9WgXcQ',
            'title' => 'Riot (official video)',
            'position' => 0,
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'watchUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], array_diff_key($video, ['id' => true, 'thumbnail' => true]));
        self::assertTrue(self::imageExists($client, Json::string($video, 'thumbnail')));
    }

    public function testTheHighQualityThumbnailIsUsedWhenTheLargestIsMissing(): void
    {
        $client = self::signedInClient();

        self::assertIsString(Json::at(self::add($client, FakeYoutube::HQ_ONLY), 'thumbnail'));
    }

    public function testAVideoIsSavedWithoutThumbnailWhenYoutubeFails(): void
    {
        $client = self::signedInClient();

        self::assertNull(Json::at(self::add($client, FakeYoutube::OFFLINE), 'thumbnail'));
        self::assertNull(Json::at(self::add($client, FakeYoutube::MISSING), 'thumbnail'));
        self::assertCount(2, self::getContainer()->get(MusicQueries::class)->videos());
    }

    public function testALinkThatIsNotYoutubeIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertStringContainsString('YouTube', self::refused($client, 'POST', '/api/admin/music/videos', ['url' => 'https://vimeo.com/1', 'title' => 'Riot']));
        self::refused($client, 'POST', '/api/admin/music/videos', ['url' => 'dQw4w9WgXcQ', 'title' => ' ']);
        self::refused($client, 'POST', '/api/admin/music/videos', ['url' => 'dQw4w9WgXcQ', 'title' => str_repeat('a', 201)]);
        self::assertSame([], self::getContainer()->get(MusicQueries::class)->videos());
    }

    public function testAVideoIsRenamed(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $video = self::send($client, 'PUT', '/api/admin/music/videos/'.$id, ['title' => 'Riot (live)']);

        self::assertSame([$id, 'Riot (live)'], [Json::string($video, 'id'), Json::string($video, 'title')]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame('Riot (live)', self::getContainer()->get(MusicQueries::class)->videos()[0]->title);
        self::refused($client, 'PUT', '/api/admin/music/videos/'.$id, ['title' => '']);
    }

    public function testAVideoIsDeletedWithItsThumbnail(): void
    {
        $client = self::signedInClient();
        $video = self::add($client);
        $kept = Json::string(self::add($client, FakeYoutube::MISSING), 'id');

        $client->jsonRequest('DELETE', '/api/admin/music/videos/'.Json::string($video, 'id'));

        self::assertResponseStatusCodeSame(204);
        self::assertFalse(self::imageExists($client, Json::string($video, 'thumbnail')));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$kept], array_map(static fn ($video): string => $video->id, self::getContainer()->get(MusicQueries::class)->videos()));
        self::send($client, 'DELETE', '/api/admin/music/videos/'.$kept);
        self::assertSame([], self::getContainer()->get(MusicQueries::class)->videos());
    }

    public function testVideosAreReordered(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client, FakeYoutube::MISSING), 'id');

        self::assertSame(1, Json::int(self::getContainer()->get(MusicQueries::class)->videos()[1]->position));

        $videos = self::send($client, 'PUT', '/api/admin/music/videos/order', ['ids' => [$b, $a]]);

        self::assertSame([$b, $a], array_map(static fn (mixed $video): string => Json::string($video, 'id'), $videos));
        self::assertSame('https://www.youtube.com/watch?v='.FakeYoutube::MISSING, Json::string($videos, 0, 'watchUrl'));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/music'), 'videos'), $videos);
        self::refused($client, 'PUT', '/api/admin/music/videos/order', ['ids' => [$b]]);
    }

    public function testAnUnknownVideoIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/music/videos/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, ['title' => 'Riot'], 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
    }

    /** @return array<mixed> */
    private static function add(KernelBrowser $client, string $youtubeId = 'dQw4w9WgXcQ'): array
    {
        return self::send($client, 'POST', '/api/admin/music/videos', ['url' => 'https://www.youtube.com/watch?v='.$youtubeId, 'title' => 'Riot']);
    }
}
