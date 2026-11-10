<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Domain\Music\Video;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class VideoTitlePayload
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Video::MAX_TITLE)]
        public string $title = '',
    ) {
    }
}
