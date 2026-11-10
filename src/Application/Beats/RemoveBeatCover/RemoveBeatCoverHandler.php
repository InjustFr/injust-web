<?php

declare(strict_types=1);

namespace App\Application\Beats\RemoveBeatCover;

use App\Application\Beats\BeatView;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Beats\BeatRepository;

final readonly class RemoveBeatCoverHandler
{
    public function __construct(
        private BeatRepository $beats,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveBeatCover $command): BeatView
    {
        $beat = $this->beats->get($command->id);
        $previous = $beat->removeCover();
        $this->transaction->commit();
        $this->images->discard($previous);

        return BeatView::of($beat);
    }
}
