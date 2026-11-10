<?php

declare(strict_types=1);

namespace App\Application\Production\OrderProductionServices;

use App\Application\Production\ProductionServiceView;
use App\Application\Transaction;
use App\Domain\Production\ProductionServiceRepository;
use App\Domain\Shared\Positions;

final readonly class OrderProductionServicesHandler
{
    public function __construct(
        private ProductionServiceRepository $services,
        private Transaction $transaction,
    ) {
    }

    /** @return list<ProductionServiceView> */
    public function __invoke(OrderProductionServices $command): array
    {
        Positions::apply($command->ids, $this->services->all());
        $this->transaction->commit();

        return array_map(ProductionServiceView::of(...), $this->services->all());
    }
}
