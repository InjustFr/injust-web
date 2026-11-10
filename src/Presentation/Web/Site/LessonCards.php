<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Lessons\LessonOfferView;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\OfferKind;

final readonly class LessonCards
{
    public function __construct(private PriceFormat $prices)
    {
    }

    /**
     * @param list<LessonOfferView> $offers
     *
     * @return list<array{format: string, offers: list<array<string, mixed>>}>
     */
    public function groups(array $offers, string $locale): array
    {
        $groups = [];
        foreach (LessonFormat::cases() as $format) {
            $cards = [];
            foreach ($offers as $offer) {
                if ($offer->format === $format->value) {
                    $cards[] = $this->card($offer, $locale);
                }
            }
            if ([] !== $cards) {
                $groups[] = ['format' => $format->value, 'offers' => $cards];
            }
        }

        return $groups;
    }

    /** @return array<string, mixed> */
    private function card(LessonOfferView $offer, string $locale): array
    {
        return [
            'id' => $offer->id,
            'title' => $offer->title->in($locale),
            'description' => $offer->description->in($locale),
            'kind' => $offer->kind,
            'sessions' => $offer->sessions,
            'minutes' => $offer->minutes,
            'price' => $this->prices->format($offer->price->cents, $locale),
            'per_lesson' => OfferKind::Pack->value === $offer->kind ? $this->prices->format((int) round($offer->price->cents / $offer->sessions), $locale) : null,
        ];
    }
}
