<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\SocialLink;

final readonly class SocialLinkView
{
    private function __construct(
        public string $id,
        public string $network,
        public string $url,
        public ?string $label,
        public int $position,
    ) {
    }

    public static function of(SocialLink $link): self
    {
        return new self($link->id()->toBase32(), $link->network()->value, $link->url(), $link->label(), $link->position());
    }
}
