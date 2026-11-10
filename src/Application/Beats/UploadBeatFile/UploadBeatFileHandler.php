<?php

declare(strict_types=1);

namespace App\Application\Beats\UploadBeatFile;

use App\Application\Beats\BeatView;
use App\Application\Beats\UploadCheck;
use App\Application\Shared\PrivateFileStorage;
use App\Application\Transaction;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\BeatRepository;

final readonly class UploadBeatFileHandler
{
    public function __construct(
        private BeatRepository $beats,
        private UploadCheck $check,
        private PrivateFileStorage $files,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadBeatFile $command): BeatView
    {
        $beat = $this->beats->get($command->id);
        $this->check->deliverable($command->path, $command->deliverable);
        $stored = $this->files->store($command->path, $command->originalName);
        try {
            $previous = $beat->useFile($command->deliverable, new BeatFile($stored->id, $stored->originalName, $stored->size, $stored->mimeType));
            $this->transaction->commit();
        } catch (\Throwable $failure) {
            $this->files->delete($stored->id);

            throw $failure;
        }
        if (null !== $previous) {
            $this->files->delete($previous->id);
        }

        return BeatView::of($beat);
    }
}
