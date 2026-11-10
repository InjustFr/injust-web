<?php

declare(strict_types=1);

namespace App\Domain\Beats;

use App\Domain\Beats\Exception\InvalidBpm;
use App\Domain\Beats\Exception\InvalidTags;
use App\Domain\Music\Slug;

final readonly class BeatDetails
{
    public const int MAX_TITLE = 120;
    public const int MIN_BPM = 40;
    public const int MAX_BPM = 300;
    public const int MAX_TAGS = 8;
    public const int MAX_TAG = 24;

    public string $title;
    public string $slug;

    /** @var list<string> */
    public array $tags;

    /** @param list<string> $tags */
    public function __construct(
        string $title,
        ?string $slug,
        public int $bpm,
        public MusicalKey $key,
        array $tags = [],
        public bool $visible = false,
    ) {
        if ($bpm < self::MIN_BPM || $bpm > self::MAX_BPM) {
            throw new InvalidBpm(self::MIN_BPM, self::MAX_BPM);
        }
        $this->title = trim($title);
        $this->slug = Slug::of('' === trim($slug ?? '') ? $title : $slug);
        $this->tags = self::tags($tags);
    }

    /**
     * @param list<string> $tags
     *
     * @return list<string>
     */
    private static function tags(array $tags): array
    {
        $tags = array_values(array_unique(array_map(static fn (string $tag): string => mb_strtolower(trim($tag)), $tags)));
        $invalid = array_filter($tags, static fn (string $tag): bool => '' === $tag || mb_strlen($tag) > self::MAX_TAG);
        if (\count($tags) > self::MAX_TAGS || [] !== $invalid) {
            throw new InvalidTags(self::MAX_TAGS, self::MAX_TAG);
        }

        return $tags;
    }
}
