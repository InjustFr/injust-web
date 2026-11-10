<?php

declare(strict_types=1);

namespace App\Application\Production;

interface ProductionQueries
{
    public function page(): ProductionPageView;

    public function text(): ProductionTextView;

    /** @return list<ProductionServiceView> */
    public function services(): array;

    /** @return list<PortfolioItemView> */
    public function portfolio(): array;

    public function service(string $id): ?ProductionServiceView;
}
