<?php

declare(strict_types=1);

namespace App\Domain\Production;

interface ProductionPageRepository
{
    public function current(): ProductionPage;

    public function save(ProductionPage $page): void;
}
