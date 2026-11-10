<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\Video;

final readonly class VideoView
{
    private function __construct(
        public string $id,
        public string $youtubeId,
        public string $title,
        public ?string $thumbnail,
        public int $position,
        public string $embedUrl,
        public string $watchUrl,
    ) {
    }

    public static function of(Video $video): self
    {
        $youtubeId = $video->youtubeId();

        return new self($video->id()->toBase32(), $youtubeId->value, $video->title(), $video->thumbnail(), $video->position(), $youtubeId->embedUrl(), $youtubeId->watchUrl());
    }
}
