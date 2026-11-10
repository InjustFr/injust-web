<?php

declare(strict_types=1);

namespace App\Application\Beats;

use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\BeatsPageRepository;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierRepository;
use App\Domain\Shared\Price;
use Symfony\Component\Uid\Ulid;

final readonly class StoredBeatsQueries implements BeatsQueries
{
    public function __construct(
        private BeatsPageRepository $pages,
        private BeatRepository $beats,
        private LicenceTierRepository $tiers,
    ) {
    }

    public function page(): BeatsPageView
    {
        $text = BeatsTextView::of($this->pages->current());
        $listed = array_values(array_filter($this->beats->all(), static fn (Beat $beat): bool => $beat->isListed()));

        return new BeatsPageView($text->intro, $text->faq, array_map(BeatView::of(...), $listed), $this->tierViews());
    }

    public function admin(): BeatsAdminView
    {
        return new BeatsAdminView(BeatsTextView::of($this->pages->current()), array_map(BeatView::of(...), $this->beats->all()), $this->tierViews());
    }

    public function beat(string $id): ?BeatView
    {
        $beat = $this->listed($id);

        return null === $beat ? null : BeatView::of($beat);
    }

    public function tiersFor(string $beatId): array
    {
        $beat = $this->listed($beatId);

        return null === $beat ? [] : array_map(static fn (LicenceTier $tier): BeatTierView => new BeatTierView(LicenceTierView::of($tier), $beat->purchasableAs($tier)), $this->tiers->all());
    }

    public function fromPrice(string $beatId): ?Price
    {
        $prices = array_map(static fn (BeatTierView $option): int => $option->tier->price->cents, array_filter($this->tiersFor($beatId), static fn (BeatTierView $option): bool => $option->purchasable));

        return [] === $prices ? null : new Price(min($prices));
    }

    /** @return list<LicenceTierView> */
    private function tierViews(): array
    {
        return array_map(LicenceTierView::of(...), $this->tiers->all());
    }

    private function listed(string $id): ?Beat
    {
        $beat = Ulid::isValid($id) ? $this->beats->find(Ulid::fromString($id)) : null;

        return true === $beat?->isListed() ? $beat : null;
    }
}
