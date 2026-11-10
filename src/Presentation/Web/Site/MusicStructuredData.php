<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Music\ReleaseView;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Track;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class MusicStructuredData
{
    private const array RELEASE_TYPES = ['single' => 'SingleRelease', 'ep' => 'EPRelease', 'album' => 'AlbumRelease'];

    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    /**
     * @param list<ReleaseView> $releases
     *
     * @return list<array<string, mixed>>
     */
    public function releases(array $releases): array
    {
        $home = $this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return array_map(fn (ReleaseView $release): array => $this->release($release, $home), $releases);
    }

    /** @return array<string, mixed> */
    private function release(ReleaseView $release, string $home): array
    {
        $url = $home.'#release-'.$release->slug;
        $node = [
            '@type' => 'MusicAlbum',
            '@id' => $url,
            'name' => $release->title,
            'url' => $url,
            'albumReleaseType' => 'https://schema.org/'.self::RELEASE_TYPES[$release->type],
            'byArtist' => ['@id' => $home.'#band'],
            'datePublished' => $release->releaseDate,
            'numTracks' => \count($release->tracks),
            'track' => array_map(self::track(...), $release->tracks),
            'sameAs' => array_map(static fn (PlatformLink $link): string => $link->url, $release->links),
        ];
        if (null !== $release->cover) {
            $node['image'] = $this->urls->generate('media_image', ['file' => $release->cover.'.webp'], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private static function track(Track $track): array
    {
        $node = ['@type' => 'MusicRecording', 'name' => $track->title, 'position' => $track->position];
        if (null !== $track->durationSeconds) {
            $node['duration'] = \sprintf('PT%dM%dS', intdiv($track->durationSeconds, 60), $track->durationSeconds % 60);
        }

        return $node;
    }
}
