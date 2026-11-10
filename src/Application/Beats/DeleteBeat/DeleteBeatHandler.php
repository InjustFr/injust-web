<?php

declare(strict_types=1);

namespace App\Application\Beats\DeleteBeat;

use App\Application\Shared\AudioStorage;
use App\Application\Shared\Images;
use App\Application\Shared\PrivateFileStorage;
use App\Application\Transaction;
use App\Domain\Beats\BeatRepository;

final readonly class DeleteBeatHandler
{
    public function __construct(
        private BeatRepository $beats,
        private PrivateFileStorage $files,
        private AudioStorage $audio,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteBeat $command): void
    {
        $beat = $this->beats->get($command->id);
        $this->beats->remove($beat);
        $this->transaction->commit();
        foreach ($beat->files() as $file) {
            $this->files->delete($file->id);
        }
        if (null !== $beat->preview()) {
            $this->audio->delete($beat->preview()->id);
        }
        $this->images->discard($beat->cover());
    }
}
