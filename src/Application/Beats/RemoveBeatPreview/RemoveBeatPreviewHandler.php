<?php

declare(strict_types=1);

namespace App\Application\Beats\RemoveBeatPreview;

use App\Application\Beats\BeatView;
use App\Application\Shared\AudioStorage;
use App\Application\Transaction;
use App\Domain\Beats\BeatRepository;

final readonly class RemoveBeatPreviewHandler
{
    public function __construct(
        private BeatRepository $beats,
        private AudioStorage $audio,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveBeatPreview $command): BeatView
    {
        $beat = $this->beats->get($command->id);
        $previous = $beat->removePreview();
        $this->transaction->commit();
        if (null !== $previous) {
            $this->audio->delete($previous->id);
        }

        return BeatView::of($beat);
    }
}
