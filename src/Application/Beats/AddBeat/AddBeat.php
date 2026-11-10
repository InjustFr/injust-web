<?php

declare(strict_types=1);

namespace App\Application\Beats\AddBeat;

use App\Domain\Beats\BeatDetails;

final readonly class AddBeat
{
    public function __construct(
        public BeatDetails $details,
    ) {
    }
}
