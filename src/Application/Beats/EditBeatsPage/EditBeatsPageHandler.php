<?php

declare(strict_types=1);

namespace App\Application\Beats\EditBeatsPage;

use App\Application\Beats\BeatsTextView;
use App\Application\Transaction;
use App\Domain\Beats\BeatsPageRepository;

final readonly class EditBeatsPageHandler
{
    public function __construct(
        private BeatsPageRepository $pages,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditBeatsPage $command): BeatsTextView
    {
        $page = $this->pages->current();
        $page->edit($command->intro, $command->faq);
        $this->pages->save($page);
        $this->transaction->commit();

        return BeatsTextView::of($page);
    }
}
