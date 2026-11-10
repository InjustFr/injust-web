<?php

declare(strict_types=1);

namespace App\Application\Production\AddPortfolioItem;

use App\Application\Production\PortfolioItemView;
use App\Application\Transaction;
use App\Domain\Production\PortfolioItem;
use App\Domain\Production\PortfolioItemRepository;
use App\Domain\Shared\Positions;

final readonly class AddPortfolioItemHandler
{
    public function __construct(
        private PortfolioItemRepository $portfolio,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddPortfolioItem $command): PortfolioItemView
    {
        $item = PortfolioItem::add($command->details, Positions::next($this->portfolio->all()));
        $this->portfolio->add($item);
        $this->transaction->commit();

        return PortfolioItemView::of($item);
    }
}
