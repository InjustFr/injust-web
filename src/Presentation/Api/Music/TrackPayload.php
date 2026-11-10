<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Domain\Music\Release;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TrackPayload
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Release::MAX_TITLE)]
        public string $title = '',
        #[Assert\Positive]
        public ?int $durationSeconds = null,
    ) {
    }
}
