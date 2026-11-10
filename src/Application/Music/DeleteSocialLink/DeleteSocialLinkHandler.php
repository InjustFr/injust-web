<?php

declare(strict_types=1);

namespace App\Application\Music\DeleteSocialLink;

use App\Application\Transaction;
use App\Domain\Music\SocialLinkRepository;

final readonly class DeleteSocialLinkHandler
{
    public function __construct(
        private SocialLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteSocialLink $command): void
    {
        $this->links->remove($this->links->get($command->id));
        $this->transaction->commit();
    }
}
