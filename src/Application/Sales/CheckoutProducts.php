<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\BeatTierView;
use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Domain\Production\Pricing;
use App\Domain\Sales\LicensedBeat;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;

final readonly class CheckoutProducts
{
    public function __construct(
        private LessonsQueries $lessons,
        private ProductionQueries $production,
        private BeatsQueries $beats,
    ) {
    }

    public function find(ProductType $type, string $id, string $locale, ?string $tierId = null): ?Purchasable
    {
        $product = match ($type) {
            ProductType::Lesson => $this->lesson($id, $locale),
            ProductType::Production => $this->service($id, $locale),
            ProductType::Beat => $this->beat($id, $tierId, $locale),
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

    private function beat(string $id, ?string $tierId, string $locale): ?Purchasable
    {
        $beat = $this->beats->beat($id);
        $option = array_find($this->beats->tiersFor($id), static fn (BeatTierView $option): bool => $option->purchasable && $option->tier->id === $tierId);
        if (null === $beat || null === $option) {
            return null;
        }
        $tier = $option->tier;

        return new Purchasable(ProductType::Beat, $beat->id, $beat->title.' — '.$tier->name->in($locale), $tier->price, null, new LicensedBeat($beat->id, $beat->title, $beat->slug, $tier->id, $tier->name, $tier->exclusive, $tier->deliverables, $tier->streamCap, $tier->copyCap, $tier->videoCap));
    }
}
