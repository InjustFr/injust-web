<?php

declare(strict_types=1);

namespace App\Application\Music\AddVideo;

use App\Application\Music\VideoThumbnails;
use App\Application\Music\VideoView;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Music\Video;
use App\Domain\Music\VideoRepository;

final readonly class AddVideoHandler
{
    public function __construct(
        private VideoRepository $videos,
        private VideoThumbnails $thumbnails,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddVideo $command): VideoView
    {
        $video = Video::add($command->youtubeId, $command->title, $this->thumbnail($command), \count($this->videos->all()));
        $this->videos->add($video);
        $this->transaction->commit();

        return VideoView::of($video);
    }

    private function thumbnail(AddVideo $command): ?string
    {
        $image = $this->thumbnails->fetch($command->youtubeId);

        return null === $image ? null : $this->images->store($image);
    }
}
