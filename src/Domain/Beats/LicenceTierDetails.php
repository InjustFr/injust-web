<?php

declare(strict_types=1);

namespace App\Domain\Beats;

use App\Domain\Beats\Exception\InvalidCap;
use App\Domain\Beats\Exception\MissingDeliverables;
use App\Domain\Beats\Exception\MissingTierPrice;
use App\Domain\Beats\Exception\UnnamedTier;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class LicenceTierDetails
{
    /** @var list<Deliverable> */
    public array $deliverables;

    /** @param list<Deliverable> $deliverables */
    public function __construct(
        public LocalizedText $name,
        public Price $price,
        public LocalizedText $summary,
        array $deliverables,
        public bool $exclusive = false,
        public ?int $streamCap = null,
        public ?int $copyCap = null,
        public ?int $videoCap = null,
    ) {
        if ($name->isEmpty()) {
            throw new UnnamedTier();
        }
        if (0 === $price->cents) {
            throw new MissingTierPrice();
        }
        if ([] === $deliverables) {
            throw new MissingDeliverables();
        }
        if ([] !== array_filter([$streamCap, $copyCap, $videoCap], static fn (?int $cap): bool => null !== $cap && $cap < 1)) {
            throw new InvalidCap();
        }
        $this->deliverables = array_values(array_filter(Deliverable::cases(), static fn (Deliverable $deliverable): bool => \in_array($deliverable, $deliverables, true)));
    }
}
