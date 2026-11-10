<?php

declare(strict_types=1);

namespace App\Application\Links\DeleteBioLink;

use App\Application\Transaction;
use App\Domain\Links\BioLinkRepository;

final readonly class DeleteBioLinkHandler
{
    public function __construct(
        private BioLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteBioLink $command): void
    {
        $this->links->remove($this->links->get($command->id));
        $this->transaction->commit();
    }
}
