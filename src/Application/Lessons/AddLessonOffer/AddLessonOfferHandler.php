<?php

declare(strict_types=1);

namespace App\Application\Lessons\AddLessonOffer;

use App\Application\Lessons\LessonOfferView;
use App\Application\Transaction;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferRepository;

final readonly class AddLessonOfferHandler
{
    public function __construct(
        private LessonOfferRepository $offers,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddLessonOffer $command): LessonOfferView
    {
        $offer = LessonOffer::add($command->details, \count($this->offers->all()));
        $this->offers->add($offer);
        $this->transaction->commit();

        return LessonOfferView::of($offer);
    }
}
