<?php

declare(strict_types=1);

namespace App\Application\Music\EditMusicText;

use App\Domain\Shared\LocalizedText;

final readonly class EditMusicText
{
    /** @param list<string> $hooks */
    public function __construct(
        public LocalizedText $tagline,
        public LocalizedText $manifesto,
        public LocalizedText $bio,
        public array $hooks,
    ) {
    }
}
