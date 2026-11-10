<?php

declare(strict_types=1);

namespace App\Application\Lessons\EditLessonOffer;

use App\Application\Lessons\LessonOfferView;
use App\Application\Transaction;
use App\Domain\Lessons\LessonOfferRepository;

final readonly class EditLessonOfferHandler
{
    public function __construct(
        private LessonOfferRepository $offers,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditLessonOffer $command): LessonOfferView
    {
        $offer = $this->offers->get($command->id);
        $offer->edit($command->details);
        $this->transaction->commit();

        return LessonOfferView::of($offer);
    }
}
