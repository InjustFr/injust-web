<?php

declare(strict_types=1);

namespace App\Application\Production\DeletePortfolioItem;

use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Production\PortfolioItemRepository;

final readonly class DeletePortfolioItemHandler
{
    public function __construct(
        private PortfolioItemRepository $portfolio,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeletePortfolioItem $command): void
    {
        $item = $this->portfolio->get($command->id);
        $this->portfolio->remove($item);
        $this->transaction->commit();
        $this->images->discard($item->cover());
    }
}
