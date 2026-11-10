<?php

declare(strict_types=1);

namespace App\Application\Music\RemoveReleaseCover;

use App\Application\Music\ReleaseView;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Music\ReleaseRepository;
use Psr\Clock\ClockInterface;

final readonly class RemoveReleaseCoverHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private Images $images,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveReleaseCover $command): ReleaseView
    {
        $release = $this->releases->get($command->id);
        $previous = $release->removeCover();
        $this->transaction->commit();
        $this->images->discard($previous);

        return ReleaseView::of($release, $this->clock->now());
    }
}
