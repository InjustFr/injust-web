<?php

declare(strict_types=1);

namespace App\Application\Beats\EditBeat;

use App\Domain\Beats\BeatDetails;
use Symfony\Component\Uid\Ulid;

final readonly class EditBeat
{
    public function __construct(
        public Ulid $id,
        public BeatDetails $details,
    ) {
    }
}
