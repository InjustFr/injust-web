<?php

declare(strict_types=1);

namespace App\Infrastructure\Platforms;

use App\Application\Music\ReleaseLookup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class YoutubeTracks
{
    private const string SEARCH = 'https://www.googleapis.com/youtube/v3/search';
    private const string VIDEOS = 'https://www.googleapis.com/youtube/v3/videos';
    private const string VIDEO_ID = '/^[A-Za-z0-9_-]{11}$/D';

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

    public function trackOf(ReleaseLookup $release): ?string
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
        $answer = $this->api->json('GET', self::SEARCH, ['query' => ['part' => 'snippet', 'type' => 'video', 'maxResults' => 10, 'q' => $this->brand.' '.$release->title], 'headers' => ['X-Goog-Api-Key' => $this->key]]);
        $items = $answer['items'] ?? null;
        if (!\is_array($items)) {
            throw new \UnexpectedValueException('YouTube answered without items.');
        }
        $title = self::normalized($release->title);
        $tracks = [];
        foreach (array_keys($items) as $index) {
            $id = PlatformApi::at($items, $index.'.id.videoId');
            if (!self::isVideoId($id) || !$this->isTopic(PlatformApi::at($items, $index.'.snippet.channelTitle'))) {
                continue;
            }
            if ($title === self::normalized(PlatformApi::at($items, $index.'.snippet.title'))) {
                return $id;
            }
            $tracks[] = $id;
        }

        return [] === $tracks ? null : $this->albumTrack($tracks, $title);
    }

    /** @param non-empty-list<string> $tracks */
    private function albumTrack(array $tracks, ?string $title): ?string
    {
        $answer = $this->api->json('GET', self::VIDEOS, ['query' => ['part' => 'snippet', 'id' => implode(',', $tracks)], 'headers' => ['X-Goog-Api-Key' => $this->key]]);
        $items = \is_array($answer['items'] ?? null) ? $answer['items'] : [];
        foreach (array_keys($items) as $index) {
            $id = PlatformApi::at($items, $index.'.id');
            if (\in_array($id, $tracks, true) && $title === self::normalized(self::albumLine(PlatformApi::at($items, $index.'.snippet.description')))) {
                return $id;
            }
        }

        return null;
    }

    private static function albumLine(mixed $description): ?string
    {
        $lines = \is_string($description) ? array_values(array_filter(array_map(trim(...), preg_split('/\R/u', $description) ?: []))) : [];
        foreach ($lines as $index => $line) {
            if (str_contains($line, ' · ')) {
                return $lines[$index + 1] ?? null;
            }
        }

        return null;
    }

    /** @phpstan-assert-if-true string $id */
    private static function isVideoId(mixed $id): bool
    {
        return \is_string($id) && 1 === preg_match(self::VIDEO_ID, $id);
    }

    private function isTopic(mixed $channel): bool
    {
        return \is_string($channel) && mb_strtolower($channel) === mb_strtolower($this->brand).' - topic';
    }

    private static function normalized(mixed $title): ?string
    {
        if (!\is_string($title)) {
            return null;
        }

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(html_entity_decode($title, \ENT_QUOTES | \ENT_HTML5))) ?? '');
    }
}
