<?php

declare(strict_types=1);

namespace App\Application\Links\OrderBioLinks;

use App\Application\Links\BioLinkView;
use App\Application\Transaction;
use App\Domain\Links\BioLinkRepository;
use App\Domain\Shared\Positions;

final readonly class OrderBioLinksHandler
{
    public function __construct(
        private BioLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    /** @return list<BioLinkView> */
    public function __invoke(OrderBioLinks $command): array
    {
        Positions::apply($command->ids, $this->links->all());
        $this->transaction->commit();

        return array_map(BioLinkView::of(...), $this->links->all());
    }
}
