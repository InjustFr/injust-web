<?php

declare(strict_types=1);

namespace App\Application\Production;

use App\Domain\Shared\LocalizedText;

final readonly class ProductionPageView
{
    /**
     * @param list<ProductionServiceView> $services
     * @param list<PortfolioItemView>     $portfolio
     */
    public function __construct(
        public LocalizedText $intro,
        public LocalizedText $process,
        public LocalizedText $faq,
        public array $services,
        public array $portfolio,
    ) {
    }
}
