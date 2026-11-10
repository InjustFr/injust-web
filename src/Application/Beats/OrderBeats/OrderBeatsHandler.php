<?php

declare(strict_types=1);

namespace App\Application\Beats\OrderBeats;

use App\Application\Beats\BeatView;
use App\Application\Transaction;
use App\Domain\Beats\BeatRepository;
use App\Domain\Shared\Positions;

final readonly class OrderBeatsHandler
{
    public function __construct(
        private BeatRepository $beats,
        private Transaction $transaction,
    ) {
    }

    /** @return list<BeatView> */
    public function __invoke(OrderBeats $command): array
    {
        Positions::apply($command->ids, $this->beats->all());
        $this->transaction->commit();

        return array_map(BeatView::of(...), $this->beats->all());
    }
}
