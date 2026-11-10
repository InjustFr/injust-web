<?php

declare(strict_types=1);

namespace App\Application\Beats\EditBeat;

use App\Application\Beats\BeatView;
use App\Application\Transaction;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\Exception\BeatSlugTaken;

final readonly class EditBeatHandler
{
    public function __construct(
        private BeatRepository $beats,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditBeat $command): BeatView
    {
        $beat = $this->beats->get($command->id);
        if ($this->beats->slugTaken($command->details->slug, $command->id)) {
            throw new BeatSlugTaken($command->details->slug);
        }
        $beat->edit($command->details);
        $this->transaction->commit();

        return BeatView::of($beat);
    }
}
