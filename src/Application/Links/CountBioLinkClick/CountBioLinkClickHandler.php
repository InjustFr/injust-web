<?php

declare(strict_types=1);

namespace App\Application\Links\CountBioLinkClick;

use App\Application\Transaction;
use App\Domain\Links\BioLinkRepository;
use App\Domain\Shared\Exception\NotFound;

final readonly class CountBioLinkClickHandler
{
    public function __construct(
        private BioLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(CountBioLinkClick $command): string
    {
        $url = $this->links->countClick($command->id) ?? throw new NotFound('bio_link', $command->id->toBase32());
        $this->transaction->commit();

        return $url;
    }
}
