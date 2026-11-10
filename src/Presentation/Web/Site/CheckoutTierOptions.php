<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\BeatTierView;
use App\Application\Beats\BeatView;
use App\Application\Beats\LicenceTierView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CheckoutTierOptions
{
    public function __construct(
        private BeatsQueries $beats,
        private TierColumns $tiers,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array{beat: ?BeatView, tier_options: list<array{id: string, name: string, price: string, deliverables: list<string>, streams: ?int, copies: ?int, videos: ?int, exclusive: bool, summary: string}>, tier_links: array<string, ?string>} */
    public function page(string $beatId, string $locale): array
    {
        $options = $this->beats->tiersFor($beatId);

        return [
            'beat' => $this->beats->beat($beatId),
            'tier_options' => $this->tiers->columns(array_map(static fn (BeatTierView $option): LicenceTierView => $option->tier, $options), $locale),
            'tier_links' => $this->links($beatId, $options),
        ];
    }

    /**
     * @param list<BeatTierView> $options
     *
     * @return array<string, ?string>
     */
    public function links(string $beatId, array $options): array
    {
        $links = [];
        foreach ($options as $option) {
            $links[$option->tier->id] = $option->purchasable ? $this->url($beatId, $option->tier->id) : null;
        }

        return $links;
    }

    public function url(string $beatId, string $tierId): string
    {
        return $this->urls->generate('checkout', ['productType' => 'beat', 'productId' => $beatId, 'tier' => $tierId]);
    }
}
