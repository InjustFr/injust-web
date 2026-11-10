<?php

declare(strict_types=1);

namespace App\Application\Music\HideReleasePlatforms;

use App\Application\Music\ReleaseView;
use App\Application\Transaction;
use App\Domain\Music\ReleaseRepository;
use Psr\Clock\ClockInterface;

final readonly class HideReleasePlatformsHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(HideReleasePlatforms $command): ReleaseView
    {
        $release = $this->releases->get($command->id);
        $release->hidePlatforms($command->platforms);
        $this->transaction->commit();

        return ReleaseView::of($release, $this->clock->now());
    }
}
