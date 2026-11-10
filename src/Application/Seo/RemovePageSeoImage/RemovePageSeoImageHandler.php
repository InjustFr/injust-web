<?php

declare(strict_types=1);

namespace App\Application\Seo\RemovePageSeoImage;

use App\Application\Seo\PageSeoView;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Seo\PageSeoRepository;

final readonly class RemovePageSeoImageHandler
{
    public function __construct(
        private PageSeoRepository $pages,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemovePageSeoImage $command): PageSeoView
    {
        $page = $this->pages->get($command->key);
        $previous = $page->removeImage();
        $this->transaction->commit();
        $this->images->discard($previous);

        return PageSeoView::of($page);
    }
}
