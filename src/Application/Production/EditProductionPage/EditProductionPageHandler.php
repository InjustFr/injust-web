<?php

declare(strict_types=1);

namespace App\Application\Production\EditProductionPage;

use App\Application\Production\ProductionTextView;
use App\Application\Transaction;
use App\Domain\Production\ProductionPageRepository;

final readonly class EditProductionPageHandler
{
    public function __construct(
        private ProductionPageRepository $pages,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditProductionPage $command): ProductionTextView
    {
        $page = $this->pages->current();
        $page->edit($command->intro, $command->process, $command->faq);
        $this->pages->save($page);
        $this->transaction->commit();

        return ProductionTextView::of($page);
    }
}
