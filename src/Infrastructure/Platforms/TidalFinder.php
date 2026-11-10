<?php

declare(strict_types=1);

namespace App\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Application\Music\PlatformFinder;
use App\Domain\Music\Platform;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTaggedItem(priority: 10)]
final readonly class TidalFinder implements PlatformFinder
{
    private const string TOKEN = 'https://auth.tidal.com/v1/oauth2/token';
    private const string SEARCH = 'https://openapi.tidal.com/v2/albums';
    private const string ALBUM = 'https://tidal.com/album/';

    public function __construct(
        private PlatformApi $api,
        #[Autowire(env: 'TIDAL_CLIENT_ID')]
        private string $clientId,
        #[Autowire(env: 'TIDAL_CLIENT_SECRET')]
        private string $clientSecret,
    ) {
    }

    public function platform(): Platform
    {
        return Platform::Tidal;
    }

    public function configured(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }

    public function find(string $upc): FinderResult
    {
        $token = $this->api->token('tidal', self::TOKEN, $this->clientId, $this->clientSecret);
        $answer = $this->api->json('GET', self::SEARCH, ['query' => ['countryCode' => 'FR', 'filter' => ['barcodeId' => $upc]], 'headers' => ['Accept' => 'application/vnd.api+json'], 'auth_bearer' => $token]);
        $albums = $answer['data'] ?? null;
        if ([] === $albums) {
            return FinderResult::notFound();
        }
        $id = PlatformApi::at($answer, 'data.0.id');
        if (!\is_string($id) || !ctype_digit($id)) {
            throw new \UnexpectedValueException('Tidal answered without a usable album id.');
        }

        return FinderResult::found(self::ALBUM.$id);
    }
}
