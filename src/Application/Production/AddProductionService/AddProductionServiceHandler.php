<?php

declare(strict_types=1);

namespace App\Application\Production\AddProductionService;

use App\Application\Production\ProductionServiceView;
use App\Application\Transaction;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceRepository;
use App\Domain\Shared\Positions;

final readonly class AddProductionServiceHandler
{
    public function __construct(
        private ProductionServiceRepository $services,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddProductionService $command): ProductionServiceView
    {
        $service = ProductionService::add($command->details, Positions::next($this->services->all()));
        $this->services->add($service);
        $this->transaction->commit();

        return ProductionServiceView::of($service);
    }
}
