<?php

declare(strict_types=1);

namespace App\Domain\Production;

use Symfony\Component\Uid\Ulid;

interface ProductionServiceRepository
{
    public function add(ProductionService $service): void;

    public function get(Ulid $id): ProductionService;

    public function find(Ulid $id): ?ProductionService;

    public function remove(ProductionService $service): void;

    /** @return list<ProductionService> */
    public function all(): array;
}
