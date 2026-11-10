<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Domain\Production\Pricing;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;

final readonly class CheckoutProducts
{
    public function __construct(
        private LessonsQueries $lessons,
        private ProductionQueries $production,
    ) {
    }

    public function find(ProductType $type, string $id, string $locale): ?Purchasable
    {
        $product = match ($type) {
            ProductType::Lesson => $this->lesson($id, $locale),
            ProductType::Production => $this->service($id, $locale),
            ProductType::Beat => null,
        };

        return null !== $product && $product->price->cents > 0 ? $product : null;
    }

    private function lesson(string $id, string $locale): ?Purchasable
    {
        $offer = $this->lessons->offer($id);

        return null === $offer ? null : new Purchasable(ProductType::Lesson, $offer->id, $offer->title->in($locale), $offer->price, $offer->format);
    }

    private function service(string $id, string $locale): ?Purchasable
    {
        $service = $this->production->service($id);

        return Pricing::Fixed->value === $service?->pricing ? new Purchasable(ProductType::Production, $service->id, $service->title->in($locale), $service->price) : null;
    }
}
