<?php

declare(strict_types=1);

namespace App\Application\Beats\UploadBeatPreview;

use App\Application\Beats\BeatView;
use App\Application\Beats\UploadCheck;
use App\Application\Shared\AudioStorage;
use App\Application\Transaction;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\BeatRepository;

final readonly class UploadBeatPreviewHandler
{
    private const string MIME_TYPE = 'audio/mpeg';

    public function __construct(
        private BeatRepository $beats,
        private UploadCheck $check,
        private AudioStorage $audio,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadBeatPreview $command): BeatView
    {
        $beat = $this->beats->get($command->id);
        $size = $this->check->preview($command->path);
        $id = $this->audio->store($command->path);
        try {
            $previous = $beat->usePreview(new BeatFile($id, $command->originalName, $size, self::MIME_TYPE));
            $this->transaction->commit();
        } catch (\Throwable $failure) {
            $this->audio->delete($id);

            throw $failure;
        }
        if (null !== $previous) {
            $this->audio->delete($previous->id);
        }

        return BeatView::of($beat);
    }
}
