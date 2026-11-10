<?php

declare(strict_types=1);

namespace App\Application\Music\AddRelease;

use App\Application\Music\ReleaseView;
use App\Application\Transaction;
use App\Domain\Music\Exception\SlugTaken;
use App\Domain\Music\Exception\UpcTaken;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Shared\Positions;
use Psr\Clock\ClockInterface;

final readonly class AddReleaseHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddRelease $command): ReleaseView
    {
        if ($this->releases->slugTaken($command->details->slug, null)) {
            throw new SlugTaken($command->details->slug);
        }
        if (null !== $command->details->upc && $this->releases->upcTaken($command->details->upc, null)) {
            throw new UpcTaken($command->details->upc);
        }
        $release = Release::draft($command->details, Positions::next($this->releases->all()));
        $this->releases->add($release);
        $this->transaction->commit();

        return ReleaseView::of($release, $this->clock->now());
    }
}
