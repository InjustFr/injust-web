<?php

declare(strict_types=1);

namespace App\Application\Beats;

use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTier;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class LicenceTierView
{
    /** @param list<Deliverable> $deliverables */
    private function __construct(
        public string $id,
        public LocalizedText $name,
        public Price $price,
        public LocalizedText $summary,
        public array $deliverables,
        public bool $exclusive,
        public ?int $streamCap,
        public ?int $copyCap,
        public ?int $videoCap,
        public int $position,
    ) {
    }

    public static function of(LicenceTier $tier): self
    {
        $details = $tier->details();

        return new self($tier->id()->toBase32(), $details->name, $details->price, $details->summary, $details->deliverables, $details->exclusive, $details->streamCap, $details->copyCap, $details->videoCap, $tier->position());
    }
}
