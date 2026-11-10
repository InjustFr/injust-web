<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Exception\InvalidSpotifyUrl;
use App\Domain\Music\SpotifyEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpotifyEmbedTest extends TestCase
{
    private const string ID = '4aawyAB9vmqN3uQ7FjRGTy';

    /** @return iterable<array{string, string}> */
    public static function accepted(): iterable
    {
        yield 'album page' => [' https://open.spotify.com/album/'.self::ID.'?si=abc ', 'album'];
        yield 'track page' => ['https://open.spotify.com/track/'.self::ID, 'track'];
        yield 'playlist page' => ['https://open.spotify.com/playlist/'.self::ID.'#top', 'playlist'];
        yield 'localized page' => ['https://open.spotify.com/intl-fr/album/'.self::ID, 'album'];
        yield 'region localized page' => ['https://open.spotify.com/intl-pt-br/track/'.self::ID, 'track'];
        yield 'embed page' => ['https://open.spotify.com/embed/album/'.self::ID, 'album'];
        yield 'uri' => ['spotify:track:'.self::ID, 'track'];
    }

    #[DataProvider('accepted')]
    public function testTheKindAndIdAreRead(string $url, string $kind): void
    {
        $embed = SpotifyEmbed::parse($url);

        self::assertSame([$kind, self::ID], [$embed->kind, $embed->id]);
    }

    public function testItGivesItsUriPageAndEmbedUrls(): void
    {
        $embed = SpotifyEmbed::parse('spotify:album:'.self::ID);

        self::assertSame('spotify:album:'.self::ID, $embed->uri());
        self::assertSame('https://open.spotify.com/album/'.self::ID, $embed->url());
        self::assertSame('https://open.spotify.com/embed/album/'.self::ID, $embed->embedUrl());
        self::assertSame(['kind' => 'album', 'id' => self::ID, 'url' => 'https://open.spotify.com/album/'.self::ID, 'embedUrl' => 'https://open.spotify.com/embed/album/'.self::ID], $embed->jsonSerialize());
    }

    /** @return iterable<array{string}> */
    public static function refused(): iterable
    {
        yield 'artist page' => ['https://open.spotify.com/artist/'.self::ID];
        yield 'short id' => ['https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGT'];
        yield 'long id' => ['https://open.spotify.com/album/'.self::ID.'x'];
        yield 'another host' => ['https://evil.test/album/'.self::ID];
        yield 'host as a prefix' => ['https://open.spotify.com.evil.test/album/'.self::ID];
        yield 'over http' => ['http://open.spotify.com/album/'.self::ID];
        yield 'text before' => ['x https://open.spotify.com/album/'.self::ID];
        yield 'uri of an artist' => ['spotify:artist:'.self::ID];
        yield 'empty' => [''];
    }

    #[DataProvider('refused')]
    public function testAnythingElseIsRefused(string $url): void
    {
        try {
            SpotifyEmbed::parse($url);
            self::fail('The link was accepted.');
        } catch (InvalidSpotifyUrl $refused) {
            self::assertSame(['music.invalid_spotify_url', ['url' => $url]], [$refused->getMessage(), $refused->parameters()]);
        }
    }
}
