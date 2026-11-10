<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FakeYoutube implements HttpClientInterface
{
    use DecoratorTrait;

    public const string OFFLINE = 'offline0000';
    public const string MISSING = 'missing0000';
    public const string HQ_ONLY = 'hqonly00000';

    private const string URL = 'https://i.ytimg.com/vi/';

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!str_starts_with($url, self::URL)) {
            return $this->client->request($method, $url, $options);
        }

        return new MockHttpClient(self::answer(...))->request($method, $url, $options);
    }

    private static function answer(string $method, string $url): MockResponse
    {
        [$id, $file] = explode('/', substr($url, \strlen(self::URL)), 2);

        return match (true) {
            self::OFFLINE === $id => new MockResponse('', ['error' => 'offline']),
            self::MISSING === $id, self::HQ_ONLY === $id && 'maxresdefault.jpg' === $file => new MockResponse('', ['http_code' => 404]),
            default => new MockResponse((string) file_get_contents(\dirname(__DIR__).'/Fixtures/large-fern.png')),
        };
    }
}
