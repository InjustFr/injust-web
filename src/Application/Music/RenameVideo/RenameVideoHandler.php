<?php

declare(strict_types=1);

namespace App\Application\Music\RenameVideo;

use App\Application\Music\VideoView;
use App\Application\Transaction;
use App\Domain\Music\VideoRepository;

final readonly class RenameVideoHandler
{
    public function __construct(
        private VideoRepository $videos,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RenameVideo $command): VideoView
    {
        $video = $this->videos->get($command->id);
        $video->rename($command->title);
        $this->transaction->commit();

        return VideoView::of($video);
    }
}
