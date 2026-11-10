<?php

declare(strict_types=1);

namespace App\Presentation\Api\Links;

use App\Domain\Links\BioLink;
use App\Domain\Music\SocialNetwork;
use App\Domain\Shared\LocalizedText;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class BioLinkPayload
{
    public function __construct(
        public LocalizedText $label = new LocalizedText(),
        #[Assert\Length(max: BioLink::MAX_URL)]
        public string $url = '',
        public ?SocialNetwork $icon = null,
        public bool $highlighted = false,
        public bool $visible = true,
    ) {
    }
}
