<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Domain\Settings\LegalIdentity;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LegalIdentityPayload
{
    public function __construct(
        #[Assert\Length(max: LegalIdentity::MAX_TEXT)]
        public string $name = '',
        #[Assert\Length(max: LegalIdentity::MAX_BUSINESS_FORM)]
        public string $businessForm = 'EI',
        public string $siren = '',
        public string $address = '',
        #[Assert\Email]
        #[Assert\Length(max: LegalIdentity::MAX_TEXT)]
        public string $email = '',
        #[Assert\Length(max: LegalIdentity::MAX_PHONE)]
        public string $phone = '',
        #[Assert\Length(max: LegalIdentity::MAX_TEXT)]
        public string $publicationDirector = '',
        #[Assert\Length(max: LegalIdentity::MAX_TEXT)]
        public string $mediatorName = '',
        #[Assert\Length(max: LegalIdentity::MAX_TEXT)]
        public string $mediatorUrl = '',
        #[Assert\Length(max: LegalIdentity::MAX_TEXT)]
        public string $hostName = '',
        public string $hostAddress = '',
        #[Assert\Length(max: LegalIdentity::MAX_PHONE)]
        public string $hostPhone = '',
        public string $vatNumber = '',
    ) {
    }
}
