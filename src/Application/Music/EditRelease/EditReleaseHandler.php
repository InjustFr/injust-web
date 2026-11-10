<?php

declare(strict_types=1);

namespace App\Application\Music\EditRelease;

use App\Application\Music\ReleaseView;
use App\Application\Transaction;
use App\Domain\Music\Exception\SlugTaken;
use App\Domain\Music\ReleaseRepository;
use Psr\Clock\ClockInterface;

final readonly class EditReleaseHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditRelease $command): ReleaseView
    {
        $release = $this->releases->get($command->id);
        if ($this->releases->slugTaken($command->details->slug, $command->id)) {
            throw new SlugTaken($command->details->slug);
        }
        $release->edit($command->details);
        $this->transaction->commit();

        return ReleaseView::of($release, $this->clock->now());
    }
}
