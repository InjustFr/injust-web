<?php

declare(strict_types=1);

namespace App\Application\Beats;

use App\Domain\Shared\LocalizedText;

final readonly class BeatsPageView
{
    /**
     * @param list<BeatView>        $beats
     * @param list<LicenceTierView> $tiers
     */
    public function __construct(
        public LocalizedText $intro,
        public LocalizedText $faq,
        public array $beats,
        public array $tiers,
    ) {
    }
}
