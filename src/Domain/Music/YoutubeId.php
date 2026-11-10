<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Music\Exception\InvalidYoutubeUrl;

final readonly class YoutubeId
{
    private const string ID = '[A-Za-z0-9_-]{11}';
    private const array HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];

    private function __construct(public string $value)
    {
    }

    public static function parse(string $url): self
    {
        $url = trim($url);
        $id = 1 === preg_match('/^'.self::ID.'$/', $url) ? $url : self::fromLink($url);

        return null === $id ? throw new InvalidYoutubeUrl($url) : new self($id);
    }

    public function embedUrl(): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$this->value;
    }

    public function watchUrl(): string
    {
        return 'https://www.youtube.com/watch?v='.$this->value;
    }

    private static function fromLink(string $url): ?string
    {
        $parts = parse_url($url);
        if (!\is_array($parts) || !\in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';

        return match (true) {
            'youtu.be' === $host => self::match('~^/('.self::ID.')$~', $path),
            !\in_array($host, self::HOSTS, true) => null,
            '/watch' === $path => self::match('/^('.self::ID.')$/', self::query($parts['query'] ?? '')),
            default => self::match('~^/(?:shorts|embed|live)/('.self::ID.')$~', $path),
        };
    }

    private static function query(string $query): string
    {
        parse_str($query, $values);

        return \is_string($values['v'] ?? null) ? $values['v'] : '';
    }

    private static function match(string $pattern, string $subject): ?string
    {
        return 1 === preg_match($pattern, $subject, $matches) ? $matches[1] : null;
    }
}
