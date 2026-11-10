<?php

declare(strict_types=1);

namespace App\Application\Settings\EditLegalIdentity;

final readonly class EditLegalIdentity
{
    public function __construct(
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
    ) {
    }
}
