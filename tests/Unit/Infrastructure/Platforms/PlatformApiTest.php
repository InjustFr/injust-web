<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Platforms;

use App\Infrastructure\Platforms\PlatformApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PlatformApiTest extends TestCase
{
    use RecordedApi;

    public function testEveryRequestHasAGenericUserAgentAndAFiveSecondLimit(): void
    {
        $seen = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = [self::header($options, 'user-agent'), $options['timeout'] ?? null, $options['max_duration'] ?? null, self::header($options, 'accept')];

            return self::json(['ok' => true]);
        });

        $answer = new PlatformApi($client, new ArrayAdapter())->json('GET', 'https://api.example.test/x', ['headers' => ['Accept' => 'application/vnd.api+json']]);

        self::assertSame(['ok' => true], $answer);
        self::assertSame(['User-Agent: injust-web', 5.0, 5.0, 'Accept: application/vnd.api+json'], $seen);
    }

    public function testATokenIsCachedPerPlatform(): void
    {
        $api = $this->api(['POST https://auth.example.test/token' => fn (): MockResponse => self::json(['access_token' => 'token-'.\count($this->requests), 'token_type' => 'Bearer', 'expires_in' => 3600])]);

        self::assertSame('token-1', $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret'));
        self::assertSame('token-1', $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret'));
        self::assertSame(['POST https://auth.example.test/token'], $this->requests);
        self::assertSame('token-2', $api->token('tidal', 'https://auth.example.test/token', 'id', 'secret'));
    }

    public function testATokenIsCachedPerClientSoRotatedCredentialsGetANewOne(): void
    {
        $api = $this->api(['POST https://auth.example.test/token' => fn (): MockResponse => self::json(['access_token' => 'token-'.\count($this->requests), 'expires_in' => 3600])]);

        self::assertSame('token-1', $api->token('spotify', 'https://auth.example.test/token', 'old-id', 'secret'));
        self::assertSame('token-2', $api->token('spotify', 'https://auth.example.test/token', 'new-id', 'secret'));
        self::assertSame('token-1', $api->token('spotify', 'https://auth.example.test/token', 'old-id', 'secret'));
    }

    public function testAShortLivedTokenIsStillCachedForASecond(): void
    {
        $clock = new MockClock('2026-11-10 09:00:00 UTC');
        $api = $this->api(['POST https://auth.example.test/token' => fn (): MockResponse => self::json(['access_token' => 'token-'.\count($this->requests), 'expires_in' => 30])], new ArrayAdapter(clock: $clock));

        self::assertSame('token-1', $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret'));
        self::assertSame('token-1', $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret'));
        $clock->sleep(2);
        self::assertSame('token-2', $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret'));
    }

    public function testTheTokenRequestUsesBasicAuthAndTheClientCredentialsGrant(): void
    {
        $seen = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = [self::header($options, 'authorization'), $options['body'] ?? null];

            return self::json(['access_token' => 'abc', 'expires_in' => 3600]);
        });

        new PlatformApi($client, new ArrayAdapter())->token('spotify', 'https://auth.example.test/token', 'id', 'secret');

        self::assertSame(['Authorization: Basic '.base64_encode('id:secret'), 'grant_type=client_credentials'], $seen);
    }

    public function testATokenExpiresAMinuteEarly(): void
    {
        $clock = new MockClock('2026-11-10 09:00:00 UTC');
        $api = $this->api(['POST https://auth.example.test/token' => fn (): MockResponse => self::json(['access_token' => 'token-'.\count($this->requests), 'expires_in' => 3600])], new ArrayAdapter(clock: $clock));

        $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret');
        $clock->sleep(3539);
        self::assertSame('token-1', $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret'));
        $clock->sleep(2);
        self::assertSame('token-2', $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret'));
    }

    public function testAnUnusableTokenAnswerIsAnError(): void
    {
        $api = $this->api(['POST https://auth.example.test/token' => self::json(['access_token' => 'abc'])]);

        $this->expectException(\UnexpectedValueException::class);

        $api->token('spotify', 'https://auth.example.test/token', 'id', 'secret');
    }

    public function testAnHttpsUrlIsReadFromAPath(): void
    {
        $data = ['albums' => ['items' => [['external_urls' => ['spotify' => 'https://open.spotify.com/album/1']]]], 'bad' => 'http://x.test', 'n' => 3];

        self::assertSame('https://open.spotify.com/album/1', PlatformApi::at($data, 'albums.items.0.external_urls.spotify'));
        self::assertNull(PlatformApi::at($data, 'albums.items.1.external_urls.spotify'));
        self::assertNull(PlatformApi::at($data, 'n.deeper'));
        self::assertSame(3, PlatformApi::at($data, 'n'));
    }
}
