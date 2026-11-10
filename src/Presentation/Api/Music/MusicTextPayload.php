<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Domain\Shared\LocalizedText;

final readonly class MusicTextPayload
{
    /** @param list<string> $hooks */
    public function __construct(
        public LocalizedText $tagline = new LocalizedText(),
        public LocalizedText $manifesto = new LocalizedText(),
        public LocalizedText $bio = new LocalizedText(),
        public array $hooks = [],
    ) {
    }
}
