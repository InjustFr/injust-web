<?php

declare(strict_types=1);

namespace App\Application\Shared\SetImageFocus;

use App\Application\Shared\ImageFocusView;
use App\Application\Shared\ImageStorage;
use App\Application\Shared\ResizedImage;
use App\Application\Transaction;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Shared\ImageFocusRepository;

final readonly class SetImageFocusHandler
{
    public function __construct(
        private ImageFocusRepository $focuses,
        private ImageStorage $storage,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(SetImageFocus $command): ImageFocusView
    {
        if (null === $this->storage->path($command->image.'.'.ResizedImage::EXTENSION)) {
            throw new NotFound('image', $command->image);
        }
        $focus = $this->focuses->get($command->image);
        $focus->moveTo($command->x, $command->y);
        if ($focus->isCentered()) {
            $this->focuses->remove($focus);
        } else {
            $this->focuses->save($focus);
        }
        $this->transaction->commit();

        return ImageFocusView::of($focus);
    }
}
