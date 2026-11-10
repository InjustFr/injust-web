<?php

declare(strict_types=1);

namespace App\Application\Lessons\DeleteLessonOffer;

use App\Application\Transaction;
use App\Domain\Lessons\LessonOfferRepository;

final readonly class DeleteLessonOfferHandler
{
    public function __construct(
        private LessonOfferRepository $offers,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteLessonOffer $command): void
    {
        $this->offers->remove($this->offers->get($command->id));
        $this->transaction->commit();
    }
}
