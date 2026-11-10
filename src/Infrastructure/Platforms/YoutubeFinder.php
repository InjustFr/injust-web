<?php

declare(strict_types=1);

namespace App\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Application\Music\PlatformFinder;
use App\Application\Music\ReleaseLookup;
use App\Domain\Music\LinkSource;
use App\Domain\Music\Platform;

final readonly class YoutubeFinder implements PlatformFinder
{
    private const string MUSIC = 'https://music.youtube.com/playlist?list=';
    private const string VIDEO = 'https://www.youtube.com/playlist?list=';

    public function __construct(private YoutubePlaylists $playlists, private Platform $platform)
    {
    }

    public function platform(): Platform
    {
        return $this->platform;
    }

    public function configured(): bool
    {
        return $this->playlists->configured();
    }

    public function find(ReleaseLookup $release): FinderResult
    {
        $id = $this->playlists->albumOf($release);
        if (null === $id) {
            return FinderResult::notFound();
        }

        return FinderResult::found((Platform::YoutubeMusic === $this->platform ? self::MUSIC : self::VIDEO).$id, LinkSource::Search);
    }
}
