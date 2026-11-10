<?php

declare(strict_types=1);

namespace App\Application\Links\AddBioLink;

use App\Application\Links\BioLinkView;
use App\Application\Transaction;
use App\Domain\Links\BioLink;
use App\Domain\Links\BioLinkRepository;
use App\Domain\Shared\Positions;

final readonly class AddBioLinkHandler
{
    public function __construct(
        private BioLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddBioLink $command): BioLinkView
    {
        $link = BioLink::add($command->label, $command->url, $command->icon, $command->highlighted, $command->visible, Positions::next($this->links->all()));
        $this->links->add($link);
        $this->transaction->commit();

        return BioLinkView::of($link);
    }
}
