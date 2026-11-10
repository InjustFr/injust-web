<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Domain\Music\Platform;
use App\Infrastructure\Platforms\TidalFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class TidalFinderTest extends TestCase
{
    use RecordedApi;

    private const string TOKEN = 'POST https://auth.tidal.com/v1/oauth2/token';
    private const string SEARCH = 'GET https://openapi.tidal.com/v2/albums?countryCode=FR&filter[barcodeId]=701518909440';

    /** @return iterable<string, array{string, string, bool}> */
    public static function credentials(): iterable
    {
        yield 'both' => ['id', 'secret', true];
        yield 'no id' => ['', 'secret', false];
        yield 'no secret' => ['id', '', false];
    }

    #[DataProvider('credentials')]
    public function testItIsConfiguredOnlyWithBothCredentials(string $id, string $secret, bool $configured): void
    {
        $finder = new TidalFinder($this->api([]), $id, $secret);

        self::assertSame([Platform::Tidal, $configured], [$finder->platform(), $finder->configured()]);
    }

    public function testTheAlbumIsFoundByBarcodeWithACachedToken(): void
    {
        $finder = self::finder($this, self::json(['data' => [['id' => '470366521', 'type' => 'albums', 'attributes' => ['title' => 'White Men are the Issue', 'barcodeId' => '701518909440']]], 'links' => ['self' => '/albums']]));

        self::assertEquals(FinderResult::found('https://tidal.com/album/470366521'), $finder->find(self::release()));
        self::assertEquals(FinderResult::found('https://tidal.com/album/470366521'), $finder->find(self::release()));
        self::assertSame([self::TOKEN, self::SEARCH, self::SEARCH], $this->requests);
        self::assertSame(['Authorization: Bearer tidal-token', 'Accept: application/vnd.api+json'], [$this->authorizations[1], $this->accepts[1]]);
    }

    public function testNoAlbumIsNotFound(): void
    {
        self::assertEquals(FinderResult::notFound(), self::finder($this, self::json(['data' => []]))->find(self::release()));
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function unusable(): iterable
    {
        yield 'no data' => [['errors' => [['code' => 'x']]]];
        yield 'data is not a list' => [['data' => 'x']];
        yield 'id is not numeric' => [['data' => [['id' => '../evil']]]];
        yield 'id is missing' => [['data' => [['type' => 'albums']]]];
        yield 'id is a number' => [['data' => [['id' => 470366521]]]];
    }

    /** @param array<mixed> $body */
    #[DataProvider('unusable')]
    public function testAnUnusableAnswerIsAFailure(array $body): void
    {
        $this->expectException(\UnexpectedValueException::class);

        self::finder($this, self::json($body))->find(self::release());
    }

    public function testAnHttpErrorIsAFailure(): void
    {
        $this->expectException(ServerExceptionInterface::class);

        self::finder($this, new MockResponse('', ['http_code' => 500]))->find(self::release());
    }

    public function testATimeoutIsAFailure(): void
    {
        $this->expectException(TransportExceptionInterface::class);

        self::finder($this, new MockResponse('', ['error' => 'Idle timeout reached']))->find(self::release());
    }

    private static function finder(self $test, MockResponse $search): TidalFinder
    {
        return new TidalFinder($test->api([self::TOKEN => self::json(['access_token' => 'tidal-token', 'token_type' => 'Bearer', 'expires_in' => 86400]), self::SEARCH => $search]), 'id', 'secret');
    }
}
