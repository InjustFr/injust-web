<?php

declare(strict_types=1);

namespace App\Application\Beats\EditLicenceTier;

use App\Domain\Beats\LicenceTierDetails;
use Symfony\Component\Uid\Ulid;

final readonly class EditLicenceTier
{
    public function __construct(
        public Ulid $id,
        public LicenceTierDetails $details,
    ) {
    }
}
