<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Youtube;

use App\Domain\Music\YoutubeId;
use App\Infrastructure\Storage\GdImageResizer;
use App\Infrastructure\Youtube\HttpVideoThumbnails;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpVideoThumbnailsTest extends TestCase
{
    private const string FERN = __DIR__.'/../../../Fixtures/large-fern.png';

    public function testTheLargestThumbnailIsFetchedAndShrunkToWebp(): void
    {
        $client = new MockHttpClient([new MockResponse((string) file_get_contents(self::FERN))]);

        $image = self::thumbnails($client)->fetch(YoutubeId::parse('dQw4w9WgXcQ'));

        self::assertNotNull($image);
        self::assertStringStartsWith('RIFF', $image->bytes);
        self::assertSame(1280, max($image->width, $image->height));
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testTheHighQualityOneIsUsedWhenTheLargestIsMissing(): void
    {
        $requested = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requested): MockResponse {
            $requested[] = $method.' '.$url.' '.var_export($options['timeout'] ?? null, true);

            return str_ends_with($url, 'maxresdefault.jpg') ? new MockResponse('', ['http_code' => 404]) : new MockResponse((string) file_get_contents(self::FERN));
        });

        self::assertNotNull(self::thumbnails($client)->fetch(YoutubeId::parse('dQw4w9WgXcQ')));
        self::assertSame(['GET https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg 5.0', 'GET https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg 5.0'], $requested);
    }

    public function testNoThumbnailWhenBothAreMissing(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 404]), new MockResponse('', ['http_code' => 404])]);

        self::assertNull(self::thumbnails($client)->fetch(YoutubeId::parse('dQw4w9WgXcQ')));
        self::assertSame(2, $client->getRequestsCount());
    }

    public function testNoThumbnailWhenYoutubeCannotBeReached(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['error' => 'offline'])]);

        self::assertNull(self::thumbnails($client)->fetch(YoutubeId::parse('dQw4w9WgXcQ')));
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testNoThumbnailWhenTheAnswerIsNotAnImage(): void
    {
        $client = new MockHttpClient([new MockResponse('<html>nope</html>')]);

        self::assertNull(self::thumbnails($client)->fetch(YoutubeId::parse('dQw4w9WgXcQ')));
    }

    public function testTheDownloadedFileIsCleanedUp(): void
    {
        $before = glob(sys_get_temp_dir().'/thumbnail*') ?: [];

        self::thumbnails(new MockHttpClient([new MockResponse((string) file_get_contents(self::FERN))]))->fetch(YoutubeId::parse('dQw4w9WgXcQ'));
        self::thumbnails(new MockHttpClient([new MockResponse('nope')]))->fetch(YoutubeId::parse('dQw4w9WgXcQ'));

        self::assertSame($before, glob(sys_get_temp_dir().'/thumbnail*') ?: []);
    }

    private static function thumbnails(MockHttpClient $client): HttpVideoThumbnails
    {
        return new HttpVideoThumbnails($client, new GdImageResizer());
    }
}
