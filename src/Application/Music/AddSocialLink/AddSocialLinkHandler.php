<?php

declare(strict_types=1);

namespace App\Application\Music\AddSocialLink;

use App\Application\Music\SocialLinkView;
use App\Application\Transaction;
use App\Domain\Music\SocialLink;
use App\Domain\Music\SocialLinkRepository;

final readonly class AddSocialLinkHandler
{
    public function __construct(
        private SocialLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddSocialLink $command): SocialLinkView
    {
        $link = SocialLink::add($command->network, $command->url, $command->label, \count($this->links->all()));
        $this->links->add($link);
        $this->transaction->commit();

        return SocialLinkView::of($link);
    }
}
