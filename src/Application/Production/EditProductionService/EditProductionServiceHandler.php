<?php

declare(strict_types=1);

namespace App\Application\Production\EditProductionService;

use App\Application\Production\ProductionServiceView;
use App\Application\Transaction;
use App\Domain\Production\ProductionServiceRepository;

final readonly class EditProductionServiceHandler
{
    public function __construct(
        private ProductionServiceRepository $services,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditProductionService $command): ProductionServiceView
    {
        $service = $this->services->get($command->id);
        $service->edit($command->details);
        $this->transaction->commit();

        return ProductionServiceView::of($service);
    }
}
