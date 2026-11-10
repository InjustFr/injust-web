<?php

declare(strict_types=1);

namespace App\Application\Music\EditSocialLink;

use App\Application\Music\SocialLinkView;
use App\Application\Transaction;
use App\Domain\Music\SocialLinkRepository;

final readonly class EditSocialLinkHandler
{
    public function __construct(
        private SocialLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditSocialLink $command): SocialLinkView
    {
        $link = $this->links->get($command->id);
        $link->edit($command->network, $command->url, $command->label);
        $this->transaction->commit();

        return SocialLinkView::of($link);
    }
}
