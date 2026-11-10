<?php

declare(strict_types=1);

namespace App\Application\Links\AddBioLink;

use App\Domain\Music\SocialNetwork;
use App\Domain\Shared\LocalizedText;

final readonly class AddBioLink
{
    public function __construct(
        public LocalizedText $label,
        public string $url,
        public ?SocialNetwork $icon,
        public bool $highlighted,
        public bool $visible,
    ) {
    }
}
