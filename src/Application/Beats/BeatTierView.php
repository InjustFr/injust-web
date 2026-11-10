<?php

declare(strict_types=1);

namespace App\Application\Beats;

final readonly class BeatTierView
{
    public function __construct(
        public LicenceTierView $tier,
        public bool $purchasable,
    ) {
    }
}
