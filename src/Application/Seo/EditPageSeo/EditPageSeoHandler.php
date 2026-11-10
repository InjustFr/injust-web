<?php

declare(strict_types=1);

namespace App\Application\Seo\EditPageSeo;

use App\Application\Seo\PageSeoView;
use App\Application\Transaction;
use App\Domain\Seo\PageSeoRepository;

final readonly class EditPageSeoHandler
{
    public function __construct(
        private PageSeoRepository $pages,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditPageSeo $command): PageSeoView
    {
        $page = $this->pages->get($command->key);
        $page->edit($command->title, $command->description);
        $this->pages->save($page);
        $this->transaction->commit();

        return PageSeoView::of($page);
    }
}
