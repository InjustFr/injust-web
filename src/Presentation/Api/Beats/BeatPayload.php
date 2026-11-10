<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\MusicalKey;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class BeatPayload
{
    /** @param list<string> $tags */
    public function __construct(
        public MusicalKey $key,
        public int $bpm,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: BeatDetails::MAX_TITLE, normalizer: 'trim')]
        public string $title = '',
        public ?string $slug = null,
        public array $tags = [],
        public bool $visible = false,
    ) {
    }

    public function details(): BeatDetails
    {
        return new BeatDetails($this->title, $this->slug, $this->bpm, $this->key, $this->tags, $this->visible);
    }
}
