<?php

declare(strict_types=1);

namespace App\Application\Music\UploadReleaseCover;

use App\Application\Music\ReleaseView;
use App\Application\Shared\ImageResizer;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Music\ReleaseRepository;
use Psr\Clock\ClockInterface;

final readonly class UploadReleaseCoverHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private ImageResizer $resizer,
        private Images $images,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadReleaseCover $command): ReleaseView
    {
        $release = $this->releases->get($command->id);
        $image = $this->images->store($this->resizer->resize($command->path));
        $previous = $release->useCover($image);
        $this->transaction->commit();
        $this->images->discard($previous);

        return ReleaseView::of($release, $this->clock->now());
    }
}
