<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Sales\ConsentTexts;
use App\Domain\Sales\ConsentKind;

final class FakeConsentTexts implements ConsentTexts
{
    public function text(ConsentKind $kind, string $locale, int $cgvVersion): string
    {
        return $locale.':'.$kind->value.':v'.$cgvVersion;
    }
}
