<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\MusicText;
use App\Domain\Shared\LocalizedText;

final readonly class MusicTextView
{
    /** @param list<string> $hooks */
    private function __construct(
        public LocalizedText $tagline,
        public LocalizedText $manifesto,
        public LocalizedText $bio,
        public array $hooks,
        public ?string $portrait,
    ) {
    }

    public static function of(MusicText $text): self
    {
        return new self($text->tagline(), $text->manifesto(), $text->bio(), $text->hooks(), $text->portrait());
    }
}
