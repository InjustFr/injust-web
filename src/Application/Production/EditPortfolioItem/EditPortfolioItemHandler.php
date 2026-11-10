<?php

declare(strict_types=1);

namespace App\Application\Production\EditPortfolioItem;

use App\Application\Production\PortfolioItemView;
use App\Application\Transaction;
use App\Domain\Production\PortfolioItemRepository;

final readonly class EditPortfolioItemHandler
{
    public function __construct(
        private PortfolioItemRepository $portfolio,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditPortfolioItem $command): PortfolioItemView
    {
        $item = $this->portfolio->get($command->id);
        $item->edit($command->details);
        $this->transaction->commit();

        return PortfolioItemView::of($item);
    }
}
