<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;

interface LegalDefaults
{
    public function body(LegalKind $kind): LocalizedText;
}
