<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final readonly class LicenceTierPayload
{
    /** @param list<Deliverable> $deliverables */
    public function __construct(
        public Price $price,
        public LocalizedText $name = new LocalizedText(),
        public LocalizedText $summary = new LocalizedText(),
        public array $deliverables = [],
        public bool $exclusive = false,
        public ?int $streamCap = null,
        public ?int $copyCap = null,
        public ?int $videoCap = null,
    ) {
    }

    public function details(): LicenceTierDetails
    {
        return new LicenceTierDetails($this->name, $this->price, $this->summary, $this->deliverables, $this->exclusive, $this->streamCap, $this->copyCap, $this->videoCap);
    }
}
