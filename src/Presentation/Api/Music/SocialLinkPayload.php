<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Domain\Music\SocialLink;
use App\Domain\Music\SocialNetwork;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SocialLinkPayload
{
    public function __construct(
        public SocialNetwork $network = SocialNetwork::Other,
        #[Assert\Length(max: SocialLink::MAX_URL)]
        public string $url = '',
        #[Assert\Length(max: SocialLink::MAX_LABEL)]
        public ?string $label = null,
    ) {
    }
}
