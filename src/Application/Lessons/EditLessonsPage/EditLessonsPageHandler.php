<?php

declare(strict_types=1);

namespace App\Application\Lessons\EditLessonsPage;

use App\Application\Lessons\LessonsTextView;
use App\Application\Transaction;
use App\Domain\Lessons\LessonsPageRepository;

final readonly class EditLessonsPageHandler
{
    public function __construct(
        private LessonsPageRepository $pages,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditLessonsPage $command): LessonsTextView
    {
        $page = $this->pages->current();
        $page->edit($command->intro, $command->faq);
        $this->pages->save($page);
        $this->transaction->commit();

        return LessonsTextView::of($page);
    }
}
