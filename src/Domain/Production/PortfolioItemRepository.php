<?php

declare(strict_types=1);

namespace App\Domain\Production;

use Symfony\Component\Uid\Ulid;

interface PortfolioItemRepository
{
    public function add(PortfolioItem $item): void;

    public function get(Ulid $id): PortfolioItem;

    public function remove(PortfolioItem $item): void;

    /** @return list<PortfolioItem> */
    public function all(): array;
}
