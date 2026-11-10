<?php

declare(strict_types=1);

namespace App\Application\Beats\RemoveBeatFile;

use App\Application\Beats\BeatView;
use App\Application\Shared\PrivateFileStorage;
use App\Application\Transaction;
use App\Domain\Beats\BeatRepository;

final readonly class RemoveBeatFileHandler
{
    public function __construct(
        private BeatRepository $beats,
        private PrivateFileStorage $files,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveBeatFile $command): BeatView
    {
        $beat = $this->beats->get($command->id);
        $previous = $beat->removeFile($command->deliverable);
        $this->transaction->commit();
        if (null !== $previous) {
            $this->files->delete($previous->id);
        }

        return BeatView::of($beat);
    }
}
