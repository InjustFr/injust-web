<?php

declare(strict_types=1);

namespace App\Application\Lessons\OrderLessonOffers;

use App\Application\Lessons\LessonOfferView;
use App\Application\Transaction;
use App\Domain\Lessons\LessonOfferRepository;
use App\Domain\Shared\Positions;

final readonly class OrderLessonOffersHandler
{
    public function __construct(
        private LessonOfferRepository $offers,
        private Transaction $transaction,
    ) {
    }

    /** @return list<LessonOfferView> */
    public function __invoke(OrderLessonOffers $command): array
    {
        Positions::apply($command->ids, $this->offers->all());
        $this->transaction->commit();

        return array_map(LessonOfferView::of(...), $this->offers->all());
    }
}
