<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Settings\LegalIdentity;

final readonly class LegalIdentityView
{
    private function __construct(
        public string $name,
        public string $businessForm,
        public string $siren,
        public string $address,
        public string $email,
        public string $phone,
        public string $publicationDirector,
        public string $mediatorName,
        public string $mediatorUrl,
        public string $hostName,
        public string $hostAddress,
        public bool $complete,
    ) {
    }

    public static function of(LegalIdentity $identity): self
    {
        return new self(
            $identity->name,
            $identity->businessForm,
            $identity->siren,
            $identity->address,
            $identity->email,
            $identity->phone,
            $identity->publicationDirector,
            $identity->mediatorName,
            $identity->mediatorUrl,
            $identity->hostName,
            $identity->hostAddress,
            $identity->isComplete(),
        );
    }
}
