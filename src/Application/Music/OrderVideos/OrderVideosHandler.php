<?php

declare(strict_types=1);

namespace App\Application\Music\OrderVideos;

use App\Application\Music\VideoView;
use App\Application\Transaction;
use App\Domain\Music\VideoRepository;
use App\Domain\Shared\Positions;

final readonly class OrderVideosHandler
{
    public function __construct(
        private VideoRepository $videos,
        private Transaction $transaction,
    ) {
    }

    /** @return list<VideoView> */
    public function __invoke(OrderVideos $command): array
    {
        Positions::apply($command->ids, $this->videos->all());
        $this->transaction->commit();

        return array_map(VideoView::of(...), $this->videos->all());
    }
}
