<?php

declare(strict_types=1);

namespace App\Application\Beats;

final readonly class BeatsAdminView
{
    /**
     * @param list<BeatView>        $beats
     * @param list<LicenceTierView> $tiers
     */
    public function __construct(
        public BeatsTextView $page,
        public array $beats,
        public array $tiers,
    ) {
    }
}
