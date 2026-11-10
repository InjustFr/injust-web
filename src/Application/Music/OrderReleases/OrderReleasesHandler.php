<?php

declare(strict_types=1);

namespace App\Application\Music\OrderReleases;

use App\Application\Music\ReleaseView;
use App\Application\Transaction;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Shared\Positions;
use Psr\Clock\ClockInterface;

final readonly class OrderReleasesHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    /** @return list<ReleaseView> */
    public function __invoke(OrderReleases $command): array
    {
        Positions::apply($command->ids, $this->releases->all());
        $this->transaction->commit();
        $now = $this->clock->now();

        return array_map(static fn (Release $release): ReleaseView => ReleaseView::of($release, $now), $this->releases->all());
    }
}
