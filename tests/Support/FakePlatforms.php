<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FakePlatforms implements HttpClientInterface
{
    use DecoratorTrait;

    public const string EVERYWHERE = '701518909440';
    public const string PARTIAL = '012345678905';
    public const string FAILING = '036000291452';
    public const string SPOTIFY_ID = '4InjustWhiteMenIssue00';
    public const string TIDAL_ID = '470366521';
    public const string YOUTUBE_ID = '7Q5mn7mihG8';

    private const array HOSTS = ['api.deezer.com', 'itunes.apple.com', 'accounts.spotify.com', 'api.spotify.com', 'auth.tidal.com', 'openapi.tidal.com', 'www.googleapis.com'];

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!\in_array(parse_url($url, \PHP_URL_HOST), self::HOSTS, true)) {
            return $this->client->request($method, $url, $options);
        }

        return new MockHttpClient(self::answer(...))->request($method, $url, $options);
    }

    private static function answer(string $method, string $url): MockResponse
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $host = parse_url($url, \PHP_URL_HOST);

        return match ($host) {
            'accounts.spotify.com', 'auth.tidal.com' => self::json(['access_token' => 'fake-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api.deezer.com' => self::deezer(substr((string) parse_url($url, \PHP_URL_PATH), \strlen('/album/upc:'))),
            'itunes.apple.com' => self::apple(self::upc($query['upc'] ?? '')),
            'www.googleapis.com' => self::youtube(self::upc($query['q'] ?? '')),
            'api.spotify.com' => self::spotify(self::upc(substr(self::upc($query['q'] ?? ''), \strlen('upc:')))),
            default => self::tidal(self::upc(\is_array($query['filter'] ?? null) ? $query['filter']['barcodeId'] ?? '' : '')),
        };
    }

    private static function deezer(string $upc): MockResponse
    {
        $id = self::id($upc, 'deezer');

        return match (true) {
            self::FAILING === $upc => new MockResponse('{"error":"busy"}', ['http_code' => 500]),
            null === $id => self::json(['error' => ['type' => 'DataException', 'message' => 'no data', 'code' => 800]]),
            default => self::json(['id' => $id, 'link' => 'https://www.deezer.com/album/'.$id]),
        };
    }

    private static function apple(string $upc): MockResponse
    {
        $id = self::id($upc, 'apple');

        return self::json(null === $id ? ['resultCount' => 0, 'results' => []] : ['resultCount' => 1, 'results' => [['wrapperType' => 'collection', 'collectionId' => $id, 'collectionViewUrl' => 'https://music.apple.com/us/album/x/'.$id.'?uo=4']]]);
    }

    private static function spotify(string $upc): MockResponse
    {
        $id = self::id($upc, 'spotify');

        return match (true) {
            self::FAILING === $upc => new MockResponse('', ['error' => 'Idle timeout reached']),
            default => self::json(['albums' => ['items' => null === $id ? [] : [['id' => $id, 'external_urls' => ['spotify' => 'https://open.spotify.com/album/'.$id]]]]]),
        };
    }

    private static function tidal(string $upc): MockResponse
    {
        $id = self::id($upc, 'tidal');

        return self::json(['data' => null === $id ? [] : [['id' => $id, 'type' => 'albums']]]);
    }

    private static function youtube(string $search): MockResponse
    {
        $video = static fn (string $id, string $title, string $channel): array => ['kind' => 'youtube#searchResult', 'id' => ['kind' => 'youtube#video', 'videoId' => $id], 'snippet' => ['title' => $title, 'channelTitle' => $channel, 'channelId' => 'UC'.md5($channel)]];

        return match ($search) {
            'Injust Failing' => self::json(['error' => ['code' => 403, 'message' => 'The request cannot be completed because you have exceeded your quota.', 'errors' => [['reason' => 'quotaExceeded', 'domain' => 'youtube.quota']]]], 403),
            'Injust White Men are the Issue' => self::json(['kind' => 'youtube#searchListResponse', 'items' => [
                $video('dEcoyCover1', 'White Men are the Issue', 'Punk Covers'),
                $video('LUdoAiwULuk', 'White Men are the Issue', 'Injust'),
                $video('lIveAtRiot1', 'White Men are the Issue (Live)', 'Injust - Topic'),
                $video(self::YOUTUBE_ID, 'White Men are the Issue', 'Injust - Topic'),
            ]]),
            default => self::json(['kind' => 'youtube#searchListResponse', 'items' => []]),
        };
    }

    private static function id(string $upc, string $platform): int|string|null
    {
        $ids = match ($upc) {
            self::EVERYWHERE => ['spotify' => self::SPOTIFY_ID, 'apple' => 6804428384, 'deezer' => 1061026552, 'tidal' => self::TIDAL_ID],
            self::PARTIAL => ['apple' => 6804428001, 'deezer' => 1061026001],
            default => [],
        };

        return $ids[$platform] ?? null;
    }

    private static function upc(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    /** @param array<mixed> $body */
    private static function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body, \JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }
}
