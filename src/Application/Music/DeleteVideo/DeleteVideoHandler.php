<?php

declare(strict_types=1);

namespace App\Application\Music\DeleteVideo;

use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Music\VideoRepository;

final readonly class DeleteVideoHandler
{
    public function __construct(
        private VideoRepository $videos,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteVideo $command): void
    {
        $video = $this->videos->get($command->id);
        $this->videos->remove($video);
        $this->transaction->commit();
        $this->images->discard($video->thumbnail());
    }
}
