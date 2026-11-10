<?php

declare(strict_types=1);

namespace App\Application\Seo\UploadPageSeoImage;

use App\Application\Seo\PageSeoView;
use App\Application\Shared\ImageResizer;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Seo\PageSeoRepository;

final readonly class UploadPageSeoImageHandler
{
    public function __construct(
        private PageSeoRepository $pages,
        private ImageResizer $resizer,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadPageSeoImage $command): PageSeoView
    {
        $image = $this->images->store($this->resizer->resize($command->path));

        $page = $this->pages->get($command->key);
        $previous = $page->useImage($image);
        $this->pages->save($page);
        $this->transaction->commit();
        $this->images->discard($previous);

        return PageSeoView::of($page);
    }
}
