<?php

declare(strict_types=1);

namespace App\Application\Production\DeleteProductionService;

use App\Application\Transaction;
use App\Domain\Production\ProductionServiceRepository;

final readonly class DeleteProductionServiceHandler
{
    public function __construct(
        private ProductionServiceRepository $services,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteProductionService $command): void
    {
        $this->services->remove($this->services->get($command->id));
        $this->transaction->commit();
    }
}
