<?php

declare(strict_types=1);

namespace App\Application\Music\FeatureRelease;

use App\Application\Music\ReleaseView;
use App\Application\Transaction;
use App\Domain\Music\ReleaseRepository;
use Psr\Clock\ClockInterface;

final readonly class FeatureReleaseHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(FeatureRelease $command): ReleaseView
    {
        $release = $this->releases->get($command->id);
        if ($command->featured) {
            foreach ($this->releases->all() as $other) {
                $other->unfeature();
            }
            $release->feature();
        } else {
            $release->unfeature();
        }
        $this->transaction->commit();

        return ReleaseView::of($release, $this->clock->now());
    }
}
