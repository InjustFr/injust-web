<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Application\Shared\ResizedImage;
use App\Domain\Music\YoutubeId;

interface VideoThumbnails
{
    public function fetch(YoutubeId $id): ?ResizedImage;
}
