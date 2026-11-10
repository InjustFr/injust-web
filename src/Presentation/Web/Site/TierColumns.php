<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Beats\LicenceTierView;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Beats\Deliverable;

final readonly class TierColumns
{
    public function __construct(
        private PriceFormat $prices,
        private LegalMarkdown $markdown,
    ) {
    }

    /**
     * @param list<LicenceTierView> $tiers
     *
     * @return list<array{id: string, name: string, price: string, deliverables: list<string>, streams: ?int, copies: ?int, videos: ?int, exclusive: bool, summary: string}>
     */
    public function columns(array $tiers, string $locale): array
    {
        return array_map(fn (LicenceTierView $tier): array => [
            'id' => $tier->id,
            'name' => $tier->name->in($locale),
            'price' => $this->prices->format($tier->price->cents, $locale),
            'deliverables' => array_map(static fn (Deliverable $deliverable): string => $deliverable->value, $tier->deliverables),
            'streams' => $tier->streamCap,
            'copies' => $tier->copyCap,
            'videos' => $tier->videoCap,
            'exclusive' => $tier->exclusive,
            'summary' => $this->markdown->toHtml($tier->summary->in($locale), []),
        ], $tiers);
    }
}
