<?php

declare(strict_types=1);

namespace App\Application\Beats\EditLicenceTier;

use App\Application\Beats\LicenceTierView;
use App\Application\Transaction;
use App\Domain\Beats\Exception\OneExclusiveTier;
use App\Domain\Beats\LicenceTierRepository;

final readonly class EditLicenceTierHandler
{
    public function __construct(
        private LicenceTierRepository $tiers,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditLicenceTier $command): LicenceTierView
    {
        $tier = $this->tiers->get($command->id);
        if ($command->details->exclusive && $this->tiers->exclusiveTaken($command->id)) {
            throw new OneExclusiveTier();
        }
        $tier->edit($command->details);
        $this->transaction->commit();

        return LicenceTierView::of($tier);
    }
}
