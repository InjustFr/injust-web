<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class ProductionServicePayload
{
    public function __construct(
        public ServiceKind $kind,
        public Pricing $pricing,
        public Price $price,
        public LocalizedText $title = new LocalizedText(),
        public LocalizedText $description = new LocalizedText(),
        public ?int $deliveryDays = null,
        public ?int $revisions = null,
    ) {
    }

    public function details(): ProductionServiceDetails
    {
        return new ProductionServiceDetails($this->title, $this->description, $this->kind, $this->pricing, $this->price, $this->deliveryDays, $this->revisions);
    }
}
