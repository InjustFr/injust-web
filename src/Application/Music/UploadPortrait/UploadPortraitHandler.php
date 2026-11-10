<?php

declare(strict_types=1);

namespace App\Application\Music\UploadPortrait;

use App\Application\Music\MusicTextView;
use App\Application\Shared\ImageResizer;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Music\MusicTextRepository;

final readonly class UploadPortraitHandler
{
    public function __construct(
        private MusicTextRepository $texts,
        private ImageResizer $resizer,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadPortrait $command): MusicTextView
    {
        $image = $this->images->store($this->resizer->resize($command->path));
        $text = $this->texts->current();
        $previous = $text->usePortrait($image);
        $this->texts->save($text);
        $this->transaction->commit();
        $this->images->discard($previous);

        return MusicTextView::of($text);
    }
}
