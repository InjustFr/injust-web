<?php

declare(strict_types=1);

namespace App\Domain\Production;

use App\Domain\Production\Exception\InvalidDeliveryDays;
use App\Domain\Production\Exception\InvalidRevisions;
use App\Domain\Production\Exception\MissingFixedPrice;
use App\Domain\Production\Exception\UntitledService;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class ProductionServiceDetails
{
    public const int MIN_DELIVERY_DAYS = 1;
    public const int MAX_DELIVERY_DAYS = 120;
    public const int MAX_REVISIONS = 10;

    public function __construct(
        public LocalizedText $title,
        public LocalizedText $description,
        public ServiceKind $kind,
        public Pricing $pricing,
        public Price $price,
        public ?int $deliveryDays = null,
        public ?int $revisions = null,
    ) {
        if ($title->isEmpty()) {
            throw new UntitledService();
        }
        if (Pricing::Fixed === $pricing && 0 === $price->cents) {
            throw new MissingFixedPrice();
        }
        if (!self::within($deliveryDays, self::MIN_DELIVERY_DAYS, self::MAX_DELIVERY_DAYS)) {
            throw new InvalidDeliveryDays();
        }
        if (!self::within($revisions, 0, self::MAX_REVISIONS)) {
            throw new InvalidRevisions();
        }
    }

    private static function within(?int $value, int $min, int $max): bool
    {
        return null === $value || ($value >= $min && $value <= $max);
    }
}
