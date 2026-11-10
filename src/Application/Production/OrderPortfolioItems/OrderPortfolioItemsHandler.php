<?php

declare(strict_types=1);

namespace App\Application\Production\OrderPortfolioItems;

use App\Application\Production\PortfolioItemView;
use App\Application\Transaction;
use App\Domain\Production\PortfolioItemRepository;
use App\Domain\Shared\Positions;

final readonly class OrderPortfolioItemsHandler
{
    public function __construct(
        private PortfolioItemRepository $portfolio,
        private Transaction $transaction,
    ) {
    }

    /** @return list<PortfolioItemView> */
    public function __invoke(OrderPortfolioItems $command): array
    {
        Positions::apply($command->ids, $this->portfolio->all());
        $this->transaction->commit();

        return array_map(PortfolioItemView::of(...), $this->portfolio->all());
    }
}
