<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

final readonly class BeatFilter
{
    /** @param list<string> $tags */
    private function __construct(
        public array $tags,
        public string $key,
        public ?int $min,
        public ?int $max,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function from(array $query): self
    {
        $tags = $query['tag'] ?? [];
        $key = $query['key'] ?? '';

        return new self(
            \is_array($tags) ? array_values(array_filter($tags, \is_string(...))) : [],
            \is_string($key) ? $key : '',
            self::bound($query['bpm_min'] ?? null),
            self::bound($query['bpm_max'] ?? null),
        );
    }

    /** @param array{tags: list<string>, key: string, bpm: int} $row */
    public function keeps(array $row): bool
    {
        return [] === array_diff($this->tags, $row['tags'])
            && ('' === $this->key || $this->key === $row['key'])
            && (null === $this->min || $row['bpm'] >= $this->min)
            && (null === $this->max || $row['bpm'] <= $this->max);
    }

    private static function bound(mixed $value): ?int
    {
        return \is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
