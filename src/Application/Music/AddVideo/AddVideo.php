<?php

declare(strict_types=1);

namespace App\Application\Music\AddVideo;

use App\Domain\Music\YoutubeId;

final readonly class AddVideo
{
    public function __construct(
        public YoutubeId $youtubeId,
        public string $title,
    ) {
    }
}
