<?php

declare(strict_types=1);

namespace App\Application\Music\OrderSocialLinks;

use App\Application\Music\SocialLinkView;
use App\Application\Transaction;
use App\Domain\Music\SocialLinkRepository;
use App\Domain\Shared\Positions;

final readonly class OrderSocialLinksHandler
{
    public function __construct(
        private SocialLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    /** @return list<SocialLinkView> */
    public function __invoke(OrderSocialLinks $command): array
    {
        Positions::apply($command->ids, $this->links->all());
        $this->transaction->commit();

        return array_map(SocialLinkView::of(...), $this->links->all());
    }
}
