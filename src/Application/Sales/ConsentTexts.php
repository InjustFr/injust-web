<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ConsentKind;

interface ConsentTexts
{
    public function text(ConsentKind $kind, string $locale, int $version): string;
}
