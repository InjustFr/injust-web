<?php

declare(strict_types=1);

namespace App\Application\Production;

use App\Domain\Production\PortfolioItemRepository;
use App\Domain\Production\ProductionPageRepository;
use App\Domain\Production\ProductionServiceRepository;
use Symfony\Component\Uid\Ulid;

final readonly class StoredProductionQueries implements ProductionQueries
{
    public function __construct(
        private ProductionPageRepository $pages,
        private ProductionServiceRepository $services,
        private PortfolioItemRepository $portfolio,
    ) {
    }

    public function page(): ProductionPageView
    {
        $text = $this->text();

        return new ProductionPageView($text->intro, $text->process, $text->faq, $this->services(), $this->portfolio());
    }

    public function text(): ProductionTextView
    {
        return ProductionTextView::of($this->pages->current());
    }

    public function services(): array
    {
        return array_map(ProductionServiceView::of(...), $this->services->all());
    }

    public function portfolio(): array
    {
        return array_map(PortfolioItemView::of(...), $this->portfolio->all());
    }

    public function service(string $id): ?ProductionServiceView
    {
        $service = Ulid::isValid($id) ? $this->services->find(Ulid::fromString($id)) : null;

        return null === $service ? null : ProductionServiceView::of($service);
    }
}
