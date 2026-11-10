<?php

declare(strict_types=1);

namespace App\Application\Music\AddSocialLink;

use App\Domain\Music\SocialNetwork;

final readonly class AddSocialLink
{
    public function __construct(
        public SocialNetwork $network,
        public string $url,
        public ?string $label,
    ) {
    }
}
