<?php

declare(strict_types=1);

namespace App\Application\Beats\OrderLicenceTiers;

use App\Application\Beats\LicenceTierView;
use App\Application\Transaction;
use App\Domain\Beats\LicenceTierRepository;
use App\Domain\Shared\Positions;

final readonly class OrderLicenceTiersHandler
{
    public function __construct(
        private LicenceTierRepository $tiers,
        private Transaction $transaction,
    ) {
    }

    /** @return list<LicenceTierView> */
    public function __invoke(OrderLicenceTiers $command): array
    {
        Positions::apply($command->ids, $this->tiers->all());
        $this->transaction->commit();

        return array_map(LicenceTierView::of(...), $this->tiers->all());
    }
}
