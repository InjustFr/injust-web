<?php

declare(strict_types=1);

namespace App\Application\Beats\AddBeat;

use App\Application\Beats\BeatView;
use App\Application\Transaction;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\Exception\BeatSlugTaken;
use App\Domain\Shared\Positions;

final readonly class AddBeatHandler
{
    public function __construct(
        private BeatRepository $beats,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddBeat $command): BeatView
    {
        if ($this->beats->slugTaken($command->details->slug, null)) {
            throw new BeatSlugTaken($command->details->slug);
        }
        $beat = Beat::add($command->details, Positions::next($this->beats->all()));
        $this->beats->add($beat);
        $this->transaction->commit();

        return BeatView::of($beat);
    }
}
