<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Domain\Music\Video;
use App\Domain\Music\YoutubeId;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class VideoPayload
{
    public function __construct(
        public string $url = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Video::MAX_TITLE)]
        public string $title = '',
    ) {
    }

    public function youtubeId(): YoutubeId
    {
        return YoutubeId::parse($this->url);
    }
}
