<?php

declare(strict_types=1);

namespace App\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Application\Music\PlatformFinder;
use App\Domain\Music\Platform;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTaggedItem(priority: 40)]
final readonly class SpotifyFinder implements PlatformFinder
{
    private const string TOKEN = 'https://accounts.spotify.com/api/token';
    private const string SEARCH = 'https://api.spotify.com/v1/search';
    private const string ALBUM = 'https://open.spotify.com/album/';

    public function __construct(
        private PlatformApi $api,
        #[Autowire(env: 'SPOTIFY_CLIENT_ID')]
        private string $clientId,
        #[Autowire(env: 'SPOTIFY_CLIENT_SECRET')]
        private string $clientSecret,
    ) {
    }

    public function platform(): Platform
    {
        return Platform::Spotify;
    }

    public function configured(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }

    public function find(string $upc): FinderResult
    {
        $token = $this->api->token('spotify', self::TOKEN, $this->clientId, $this->clientSecret);
        $answer = $this->api->json('GET', self::SEARCH, ['query' => ['q' => 'upc:'.$upc, 'type' => 'album', 'limit' => 1], 'auth_bearer' => $token]);
        if (!\is_array(PlatformApi::at($answer, 'albums.items'))) {
            throw new \UnexpectedValueException('Spotify answered without albums.');
        }
        if ([] === PlatformApi::at($answer, 'albums.items')) {
            return FinderResult::notFound();
        }
        $id = PlatformApi::at($answer, 'albums.items.0.id');
        if (!\is_string($id) || 1 !== preg_match('/^[A-Za-z0-9]{22}$/D', $id)) {
            throw new \UnexpectedValueException('Spotify answered an album without a usable id.');
        }

        return FinderResult::found(self::ALBUM.$id);
    }
}
