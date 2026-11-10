<?php

declare(strict_types=1);

namespace App\Application\Links\EditBioLink;

use App\Domain\Music\SocialNetwork;
use App\Domain\Shared\LocalizedText;
use Symfony\Component\Uid\Ulid;

final readonly class EditBioLink
{
    public function __construct(
        public Ulid $id,
        public LocalizedText $label,
        public string $url,
        public ?SocialNetwork $icon,
        public bool $highlighted,
        public bool $visible,
    ) {
    }
}
