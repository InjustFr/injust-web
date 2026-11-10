<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\BeatTierView;
use App\Application\Beats\BeatView;
use App\Domain\Beats\MusicalKey;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BeatRows
{
    public function __construct(
        private BeatsQueries $beats,
        private PriceFormat $prices,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
        private CheckoutTierOptions $checkout,
        private ImageFocusClasses $focus,
    ) {
    }

    /**
     * @param list<BeatView> $beats
     *
     * @return list<array{id: string, slug: string, title: string, bpm: int, key: string, keyLabel: string, tags: list<string>, cover: ?string, coverClass: string, preview: ?string, price: ?string, cents: ?int, licences: array<string, ?string>, buy: ?string}>
     */
    public function rows(array $beats): array
    {
        return array_map($this->row(...), $beats);
    }

    /**
     * @param list<array{key: string, keyLabel: string}> $rows
     *
     * @return list<array{value: string, label: string}>
     */
    public static function keys(array $rows): array
    {
        $labels = array_column($rows, 'keyLabel', 'key');

        return array_values(array_map(
            static fn (MusicalKey $key): array => ['value' => $key->value, 'label' => $labels[$key->value]],
            array_filter(MusicalKey::cases(), static fn (MusicalKey $key): bool => isset($labels[$key->value])),
        ));
    }

    /**
     * @param list<array{tags: list<string>}> $rows
     *
     * @return list<string>
     */
    public static function tags(array $rows): array
    {
        $tags = array_values(array_unique(array_merge(...array_column($rows, 'tags'))));
        sort($tags);

        return $tags;
    }

    /** @return array{id: string, slug: string, title: string, bpm: int, key: string, keyLabel: string, tags: list<string>, cover: ?string, coverClass: string, preview: ?string, price: ?string, cents: ?int, licences: array<string, ?string>, buy: ?string} */
    private function row(BeatView $beat): array
    {
        $options = $this->beats->tiersFor($beat->id);
        $cheapest = self::cheapest($options);
        $price = $cheapest?->tier->price;

        return [
            'id' => $beat->id,
            'slug' => $beat->slug,
            'title' => $beat->title,
            'bpm' => $beat->bpm,
            'key' => $beat->key,
            'keyLabel' => $this->keyLabel($beat->key),
            'tags' => $beat->tags,
            'cover' => null === $beat->cover ? null : $this->urls->generate('media_image', ['file' => $beat->cover.'.webp']),
            'coverClass' => $this->focus->of($beat->cover),
            'preview' => null === $beat->preview ? null : $this->urls->generate('media_audio', ['id' => $beat->preview->id]),
            'price' => null === $price ? null : $this->translator->trans('site.beats.from', ['price' => $this->prices->format($price->cents, $this->translator->getLocale())]),
            'cents' => $price?->cents,
            'licences' => $this->checkout->links($beat->id, $options),
            'buy' => null === $cheapest ? null : $this->checkout->url($beat->id, $cheapest->tier->id),
        ];
    }

    /** @param list<BeatTierView> $options */
    private static function cheapest(array $options): ?BeatTierView
    {
        $purchasable = array_filter($options, static fn (BeatTierView $option): bool => $option->purchasable);
        usort($purchasable, static fn (BeatTierView $a, BeatTierView $b): int => $a->tier->price->cents <=> $b->tier->price->cents);

        return $purchasable[0] ?? null;
    }

    private function keyLabel(string $key): string
    {
        $cut = (int) strrpos($key, '-');

        return $this->translator->trans('site.beats.key', [
            'note' => $this->translator->trans('site.beats.notes.'.substr($key, 0, $cut)),
            'mode' => substr($key, $cut + 1),
        ]);
    }
}
