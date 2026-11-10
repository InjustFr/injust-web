<?php

declare(strict_types=1);

namespace App\Application\Music\DeleteRelease;

use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Music\ReleaseRepository;

final readonly class DeleteReleaseHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteRelease $command): void
    {
        $release = $this->releases->get($command->id);
        $this->releases->remove($release);
        $this->transaction->commit();
        $this->images->discard($release->cover());
    }
}
