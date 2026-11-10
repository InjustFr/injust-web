<?php

declare(strict_types=1);

namespace App\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Application\Music\PlatformFinder;
use App\Domain\Music\Platform;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 20)]
final readonly class DeezerFinder implements PlatformFinder
{
    private const string URL = 'https://api.deezer.com/album/upc:';
    private const int NO_DATA = 800;
    private const string ALBUM = 'https://www.deezer.com/album/';

    public function __construct(private PlatformApi $api)
    {
    }

    public function platform(): Platform
    {
        return Platform::Deezer;
    }

    public function configured(): bool
    {
        return true;
    }

    public function find(string $upc): FinderResult
    {
        $album = $this->api->json('GET', self::URL.$upc);
        if (\array_key_exists('error', $album)) {
            return self::NO_DATA === PlatformApi::at($album, 'error.code') ? FinderResult::notFound() : throw new \UnexpectedValueException('Deezer answered an error.');
        }
        $id = $album['id'] ?? null;

        return \is_int($id) && $id > 0 ? FinderResult::found(self::ALBUM.$id) : throw new \UnexpectedValueException('Deezer answered an album without a usable id.');
    }
}
