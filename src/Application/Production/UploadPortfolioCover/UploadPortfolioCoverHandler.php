<?php

declare(strict_types=1);

namespace App\Application\Production\UploadPortfolioCover;

use App\Application\Production\PortfolioItemView;
use App\Application\Shared\ImageResizer;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Production\PortfolioItemRepository;

final readonly class UploadPortfolioCoverHandler
{
    public function __construct(
        private PortfolioItemRepository $portfolio,
        private ImageResizer $resizer,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadPortfolioCover $command): PortfolioItemView
    {
        $item = $this->portfolio->get($command->id);
        $image = $this->images->store($this->resizer->resize($command->path));
        $previous = $item->useCover($image);
        $this->transaction->commit();
        $this->images->discard($previous);

        return PortfolioItemView::of($item);
    }
}
