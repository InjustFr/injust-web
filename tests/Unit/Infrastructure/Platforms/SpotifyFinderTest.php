<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Domain\Music\Platform;
use App\Infrastructure\Platforms\SpotifyFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class SpotifyFinderTest extends TestCase
{
    use RecordedApi;

    private const string TOKEN = 'POST https://accounts.spotify.com/api/token';
    private const string SEARCH = 'GET https://api.spotify.com/v1/search?q=upc:701518909440&type=album&limit=1';

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
        $finder = new SpotifyFinder($this->api([]), $id, $secret);

        self::assertSame([Platform::Spotify, $configured], [$finder->platform(), $finder->configured()]);
    }

    public function testTheAlbumIsSearchedByUpcWithACachedToken(): void
    {
        $bearer = [];
        $finder = new SpotifyFinder($this->api([
            self::TOKEN => self::json(['access_token' => 'spotify-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            self::SEARCH => self::json(['albums' => ['items' => [['id' => '4aawyAB9vmqN3uQ7FjRGTy', 'external_urls' => ['spotify' => 'https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy']]]]]),
        ]), 'id', 'secret');

        self::assertEquals(FinderResult::found('https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy'), $finder->find('701518909440'));
        self::assertEquals(FinderResult::found('https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy'), $finder->find('701518909440'));
        self::assertSame([self::TOKEN, self::SEARCH, self::SEARCH], $this->requests);
    }

    public function testNoAlbumIsNotFoundAndTheSearchSendsTheBearerToken(): void
    {
        $finder = new SpotifyFinder($this->api([
            self::TOKEN => self::json(['access_token' => 'spotify-token', 'expires_in' => 3600]),
            self::SEARCH => self::json(['albums' => ['items' => []]]),
        ]), 'id', 'secret');

        self::assertEquals(FinderResult::notFound(), $finder->find('701518909440'));
        self::assertSame(['Authorization: Basic '.base64_encode('id:secret'), 'Authorization: Bearer spotify-token'], $this->authorizations);
    }

    public function testTheLinkIsBuiltFromTheAlbumIdWhateverUrlSpotifyAnswers(): void
    {
        $finder = new SpotifyFinder($this->api([
            self::TOKEN => self::json(['access_token' => 'spotify-token', 'expires_in' => 3600]),
            self::SEARCH => self::json(['albums' => ['items' => [['id' => '4aawyAB9vmqN3uQ7FjRGTy', 'external_urls' => ['spotify' => 'https://evil.example/album/x']]]]]),
        ]), 'id', 'secret');

        self::assertEquals(FinderResult::found('https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy'), $finder->find('701518909440'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function unusableIds(): iterable
    {
        yield 'missing' => [null];
        yield 'too short' => ['4aawyAB9vmqN3uQ7FjRGT'];
        yield 'too long' => ['4aawyAB9vmqN3uQ7FjRGTyy'];
        yield 'path' => ['4aawyAB9vmqN3uQ7/../Ty'];
        yield 'trailing' => ["4aawyAB9vmqN3uQ7FjRGTy\n"];
        yield 'number' => [42];
    }

    #[DataProvider('unusableIds')]
    public function testAnAlbumWithoutAUsableIdIsAFailure(mixed $id): void
    {
        $finder = new SpotifyFinder($this->api([
            self::TOKEN => self::json(['access_token' => 'spotify-token', 'expires_in' => 3600]),
            self::SEARCH => self::json(['albums' => ['items' => [['id' => $id, 'external_urls' => ['spotify' => 'https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy']]]]]),
        ]), 'id', 'secret');

        $this->expectException(\UnexpectedValueException::class);

        $finder->find('701518909440');
    }

    public function testAnAnswerWithoutAlbumsIsAFailure(): void
    {
        $finder = new SpotifyFinder($this->api([
            self::TOKEN => self::json(['access_token' => 'spotify-token', 'expires_in' => 3600]),
            self::SEARCH => self::json(['tracks' => []]),
        ]), 'id', 'secret');

        $this->expectException(\UnexpectedValueException::class);

        $finder->find('701518909440');
    }

    public function testRefusedCredentialsAreAFailure(): void
    {
        $finder = new SpotifyFinder($this->api([self::TOKEN => self::json(['error' => 'invalid_client'], 400)]), 'id', 'secret');

        $this->expectException(ClientExceptionInterface::class);

        $finder->find('701518909440');
    }

    public function testATimeoutIsAFailure(): void
    {
        $finder = new SpotifyFinder($this->api([
            self::TOKEN => self::json(['access_token' => 'spotify-token', 'expires_in' => 3600]),
            self::SEARCH => new MockResponse('', ['error' => 'Idle timeout reached']),
        ]), 'id', 'secret');

        $this->expectException(TransportExceptionInterface::class);

        $finder->find('701518909440');
    }
}
