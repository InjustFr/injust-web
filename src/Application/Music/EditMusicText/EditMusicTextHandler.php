<?php

declare(strict_types=1);

namespace App\Application\Music\EditMusicText;

use App\Application\Music\MusicTextView;
use App\Application\Transaction;
use App\Domain\Music\MusicTextRepository;

final readonly class EditMusicTextHandler
{
    public function __construct(
        private MusicTextRepository $texts,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditMusicText $command): MusicTextView
    {
        $text = $this->texts->current();
        $text->edit($command->tagline, $command->manifesto, $command->bio, $command->hooks);
        $this->texts->save($text);
        $this->transaction->commit();

        return MusicTextView::of($text);
    }
}
