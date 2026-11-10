<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Beats\Deliverable;
use App\Domain\Shared\LocalizedText;

/**
 * @phpstan-type LicensedBeatData array{beatId: string, title: string, slug: string, tierId: string, tierName: array{fr: string, en: string}, exclusive: bool, deliverables: list<string>, streamCap: ?int, copyCap: ?int, videoCap: ?int}
 */
final readonly class LicensedBeat
{
    /** @param list<Deliverable> $deliverables */
    public function __construct(
        public string $beatId,
        public string $title,
        public string $slug,
        public string $tierId,
        public LocalizedText $tierName,
        public bool $exclusive,
        public array $deliverables,
        public ?int $streamCap,
        public ?int $copyCap,
        public ?int $videoCap,
    ) {
    }

    /** @param LicensedBeatData $data */
    public static function fromArray(array $data): self
    {
        return new self($data['beatId'], $data['title'], $data['slug'], $data['tierId'], new LocalizedText($data['tierName']['fr'], $data['tierName']['en']), $data['exclusive'], array_map(Deliverable::from(...), $data['deliverables']), $data['streamCap'], $data['copyCap'], $data['videoCap']);
    }

    /** @return LicensedBeatData */
    public function toArray(): array
    {
        return [
            'beatId' => $this->beatId,
            'title' => $this->title,
            'slug' => $this->slug,
            'tierId' => $this->tierId,
            'tierName' => $this->tierName->jsonSerialize(),
            'exclusive' => $this->exclusive,
            'deliverables' => array_map(static fn (Deliverable $deliverable): string => $deliverable->value, $this->deliverables),
            'streamCap' => $this->streamCap,
            'copyCap' => $this->copyCap,
            'videoCap' => $this->videoCap,
        ];
    }

    public function delivers(Deliverable $deliverable): bool
    {
        return \in_array($deliverable, $this->deliverables, true);
    }
}
