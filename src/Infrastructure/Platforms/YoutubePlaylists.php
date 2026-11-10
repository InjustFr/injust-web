<?php

declare(strict_types=1);

namespace App\Infrastructure\Platforms;

use App\Application\Music\ReleaseLookup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class YoutubePlaylists
{
    private const string SEARCH = 'https://www.googleapis.com/youtube/v3/search';
    private const string ALBUM_ID = '/^OLAK5uy_[A-Za-z0-9_-]{20,40}$/D';
    private const array RELEASE_MARKS = ['/^(album|single|ep) - /u', '/ - (single|ep)$/u'];

    private ?ReleaseLookup $asked = null;

    private string|\Throwable|null $answer = null;

    public function __construct(
        private readonly PlatformApi $api,
        #[Autowire(env: 'YOUTUBE_API_KEY')]
        private readonly string $key,
        #[Autowire(param: 'brand')]
        private readonly string $brand,
    ) {
    }

    public function configured(): bool
    {
        return '' !== $this->key;
    }

    public function albumOf(ReleaseLookup $release): ?string
    {
        if ($release !== $this->asked) {
            $this->asked = $release;
            try {
                $this->answer = $this->search($release);
            } catch (\Throwable $failure) {
                $this->answer = $failure;
            }
        }

        return $this->answer instanceof \Throwable ? throw $this->answer : $this->answer;
    }

    private function search(ReleaseLookup $release): ?string
    {
        $answer = $this->api->json('GET', self::SEARCH, ['query' => ['part' => 'snippet', 'type' => 'playlist', 'maxResults' => 10, 'q' => $this->brand.' '.$release->title], 'headers' => ['X-Goog-Api-Key' => $this->key]]);
        $items = $answer['items'] ?? null;
        if (!\is_array($items)) {
            throw new \UnexpectedValueException('YouTube answered without items.');
        }
        $title = self::normalized($release->title);
        foreach (array_keys($items) as $index) {
            $id = PlatformApi::at($items, $index.'.id.playlistId');
            if (\is_string($id) && 1 === preg_match(self::ALBUM_ID, $id) && $this->isBand(PlatformApi::at($items, $index.'.snippet.channelTitle')) && $title === self::normalized(PlatformApi::at($items, $index.'.snippet.title'))) {
                return $id;
            }
        }

        return null;
    }

    private function isBand(mixed $channel): bool
    {
        $band = mb_strtolower($this->brand);

        return \is_string($channel) && \in_array(mb_strtolower($channel), [$band, $band.' - topic'], true);
    }

    private static function normalized(mixed $title): ?string
    {
        if (!\is_string($title)) {
            return null;
        }
        $bare = preg_replace(self::RELEASE_MARKS, '', mb_strtolower(trim($title))) ?? '';

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $bare) ?? '');
    }
}
