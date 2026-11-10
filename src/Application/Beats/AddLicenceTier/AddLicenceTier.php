<?php

declare(strict_types=1);

namespace App\Application\Beats\AddLicenceTier;

use App\Domain\Beats\LicenceTierDetails;

final readonly class AddLicenceTier
{
    public function __construct(
        public LicenceTierDetails $details,
    ) {
    }
}
