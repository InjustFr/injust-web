<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Production\ProductionServiceView;
use App\Application\Shared\LegalMarkdown;

final readonly class ProductionCards
{
    public function __construct(
        private PriceFormat $prices,
        private LegalMarkdown $markdown,
    ) {
    }

    /**
     * @param list<ProductionServiceView> $services
     *
     * @return list<array<string, mixed>>
     */
    public function cards(array $services, string $locale): array
    {
        return array_map(fn (ProductionServiceView $service): array => [
            'id' => $service->id,
            'kind' => $service->kind,
            'title' => $service->title->in($locale),
            'description' => $this->markdown->toHtml($service->description->in($locale), []),
            'pricing' => $service->pricing,
            'price' => 0 === $service->price->cents ? null : $this->prices->format($service->price->cents, $locale),
            'delivery_days' => $service->deliveryDays,
            'revisions' => $service->revisions,
        ], $services);
    }
}
