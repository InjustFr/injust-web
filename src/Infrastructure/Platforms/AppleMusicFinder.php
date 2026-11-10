<?php

declare(strict_types=1);

namespace App\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Application\Music\PlatformFinder;
use App\Domain\Music\Platform;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 30)]
final readonly class AppleMusicFinder implements PlatformFinder
{
    private const string URL = 'https://itunes.apple.com/lookup';
    private const string ALBUM = 'https://music.apple.com/album/';

    public function __construct(private PlatformApi $api)
    {
    }

    public function platform(): Platform
    {
        return Platform::AppleMusic;
    }

    public function configured(): bool
    {
        return true;
    }

    public function find(string $upc): FinderResult
    {
        $answer = $this->api->json('GET', self::URL, ['query' => ['upc' => $upc, 'entity' => 'album']]);
        $results = $answer['results'] ?? null;
        if (!\is_array($results)) {
            throw new \UnexpectedValueException('Apple answered without results.');
        }
        if ([] === $results) {
            return FinderResult::notFound();
        }
        $id = PlatformApi::at($answer, 'results.0.collectionId');

        return \is_int($id) ? FinderResult::found(self::ALBUM.$id) : throw new \UnexpectedValueException('Apple answered an album without collectionId.');
    }
}
