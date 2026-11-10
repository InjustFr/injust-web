<?php

declare(strict_types=1);

namespace App\Application\Production;

use App\Domain\Production\ProductionService;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class ProductionServiceView
{
    private function __construct(
        public string $id,
        public string $kind,
        public LocalizedText $title,
        public LocalizedText $description,
        public string $pricing,
        public Price $price,
        public ?int $deliveryDays,
        public ?int $revisions,
        public int $position,
    ) {
    }

    public static function of(ProductionService $service): self
    {
        $details = $service->details();

        return new self($service->id()->toBase32(), $details->kind->value, $details->title, $details->description, $details->pricing->value, $details->price, $details->deliveryDays, $details->revisions, $service->position());
    }
}
