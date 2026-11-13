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
    private const string MUSIC = 'https://music.youtube.com/watch?v=';
    private const string VIDEO = 'https://www.youtube.com/watch?v=';

    public function __construct(private YoutubeTracks $tracks, private Platform $platform)
    {
    }

    public function platform(): Platform
    {
        return $this->platform;
    }

    public function configured(): bool
    {
        return $this->tracks->configured();
    }

    public function find(ReleaseLookup $release): FinderResult
    {
        $id = $this->tracks->trackOf($release);
        if (null === $id) {
            return FinderResult::notFound();
        }

        return FinderResult::found((Platform::YoutubeMusic === $this->platform ? self::MUSIC : self::VIDEO).$id, LinkSource::Search);
    }
}
