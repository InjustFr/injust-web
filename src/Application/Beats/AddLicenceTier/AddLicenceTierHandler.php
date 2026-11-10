<?php

declare(strict_types=1);

namespace App\Application\Beats\AddLicenceTier;

use App\Application\Beats\LicenceTierView;
use App\Application\Transaction;
use App\Domain\Beats\Exception\OneExclusiveTier;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierRepository;
use App\Domain\Shared\Positions;

final readonly class AddLicenceTierHandler
{
    public function __construct(
        private LicenceTierRepository $tiers,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddLicenceTier $command): LicenceTierView
    {
        if ($command->details->exclusive && $this->tiers->exclusiveTaken(null)) {
            throw new OneExclusiveTier();
        }
        $tier = LicenceTier::add($command->details, Positions::next($this->tiers->all()));
        $this->tiers->add($tier);
        $this->transaction->commit();

        return LicenceTierView::of($tier);
    }
}
