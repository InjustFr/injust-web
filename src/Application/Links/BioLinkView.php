<?php

declare(strict_types=1);

namespace App\Application\Links;

use App\Domain\Links\BioLink;
use App\Domain\Shared\LocalizedText;

final readonly class BioLinkView
{
    private function __construct(
        public string $id,
        public LocalizedText $label,
        public string $url,
        public ?string $icon,
        public bool $highlighted,
        public bool $visible,
        public int $clicks,
        public int $position,
    ) {
    }

    public static function of(BioLink $link): self
    {
        return new self($link->id()->toBase32(), $link->label(), $link->url(), $link->icon()?->value, $link->highlighted(), $link->visible(), $link->clicks(), $link->position());
    }
}
