<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Music\Exception\InvalidSpotifyUrl;

final readonly class SpotifyEmbed implements \JsonSerializable
{
    private const string PATTERN = '~^(?:spotify:|https://open\.spotify\.com/(?:intl-[a-z]{2}(?:-[a-z]{2})?/)?(?:embed/)?)(album|track|playlist)[:/]([A-Za-z0-9]{22})(?:[?#].*)?$~';

    private function __construct(
        public string $kind,
        public string $id,
    ) {
    }

    public static function parse(string $url): self
    {
        if (1 !== preg_match(self::PATTERN, trim($url), $matches)) {
            throw new InvalidSpotifyUrl($url);
        }

        return new self($matches[1], $matches[2]);
    }

    public function uri(): string
    {
        return 'spotify:'.$this->kind.':'.$this->id;
    }

    public function url(): string
    {
        return 'https://open.spotify.com/'.$this->kind.'/'.$this->id;
    }

    public function embedUrl(): string
    {
        return 'https://open.spotify.com/embed/'.$this->kind.'/'.$this->id;
    }

    /** @return array{kind: string, id: string, url: string, embedUrl: string} */
    public function jsonSerialize(): array
    {
        return ['kind' => $this->kind, 'id' => $this->id, 'url' => $this->url(), 'embedUrl' => $this->embedUrl()];
    }
}
