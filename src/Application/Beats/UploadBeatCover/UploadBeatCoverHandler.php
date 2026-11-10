<?php

declare(strict_types=1);

namespace App\Application\Beats\UploadBeatCover;

use App\Application\Beats\BeatView;
use App\Application\Shared\ImageResizer;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Beats\BeatRepository;

final readonly class UploadBeatCoverHandler
{
    public function __construct(
        private BeatRepository $beats,
        private ImageResizer $resizer,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadBeatCover $command): BeatView
    {
        $beat = $this->beats->get($command->id);
        $image = $this->images->store($this->resizer->resize($command->path));
        $previous = $beat->useCover($image);
        $this->transaction->commit();
        $this->images->discard($previous);

        return BeatView::of($beat);
    }
}
