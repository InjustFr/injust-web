<?php

declare(strict_types=1);

namespace App\Application\Beats\DeleteLicenceTier;

use App\Application\Transaction;
use App\Domain\Beats\LicenceTierRepository;

final readonly class DeleteLicenceTierHandler
{
    public function __construct(
        private LicenceTierRepository $tiers,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteLicenceTier $command): void
    {
        $this->tiers->remove($this->tiers->get($command->id));
        $this->transaction->commit();
    }
}
