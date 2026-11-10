<?php

declare(strict_types=1);

namespace App\Application\Music\RemovePortrait;

use App\Application\Music\MusicTextView;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Music\MusicTextRepository;

final readonly class RemovePortraitHandler
{
    public function __construct(
        private MusicTextRepository $texts,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemovePortrait $command): MusicTextView
    {
        $text = $this->texts->current();
        $previous = $text->removePortrait();
        $this->transaction->commit();
        $this->images->discard($previous);

        return MusicTextView::of($text);
    }
}
