<?php

declare(strict_types=1);

namespace App\Application\Music\EditSocialLink;

use App\Domain\Music\SocialNetwork;
use Symfony\Component\Uid\Ulid;

final readonly class EditSocialLink
{
    public function __construct(
        public Ulid $id,
        public SocialNetwork $network,
        public string $url,
        public ?string $label,
    ) {
    }
}
