<?php

declare(strict_types=1);

namespace App\Application\Production\RemovePortfolioCover;

use App\Application\Production\PortfolioItemView;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Production\PortfolioItemRepository;

final readonly class RemovePortfolioCoverHandler
{
    public function __construct(
        private PortfolioItemRepository $portfolio,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemovePortfolioCover $command): PortfolioItemView
    {
        $item = $this->portfolio->get($command->id);
        $previous = $item->removeCover();
        $this->transaction->commit();
        $this->images->discard($previous);

        return PortfolioItemView::of($item);
    }
}
