<?php

declare(strict_types=1);

namespace App\Application\Links\EditBioLink;

use App\Application\Links\BioLinkView;
use App\Application\Transaction;
use App\Domain\Links\BioLinkRepository;

final readonly class EditBioLinkHandler
{
    public function __construct(
        private BioLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditBioLink $command): BioLinkView
    {
        $link = $this->links->get($command->id);
        $link->edit($command->label, $command->url, $command->icon, $command->highlighted, $command->visible);
        $this->transaction->commit();

        return BioLinkView::of($link);
    }
}
