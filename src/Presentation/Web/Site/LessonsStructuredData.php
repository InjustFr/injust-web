<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Lessons\LessonOfferView;
use App\Domain\Shared\Price;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LessonsStructuredData
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<LessonOfferView> $offers
     *
     * @return array<string, mixed>
     */
    public function service(array $offers, string $locale): array
    {
        $url = $this->urls->generate('lessons', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $name = $this->translator->trans('site.lessons.service');

        return [
            '@type' => 'Service',
            '@id' => $url.'#service',
            'name' => $name,
            'serviceType' => $name,
            'url' => $url,
            'provider' => ['@id' => $this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL).'#band'],
            'offers' => array_map(static fn (LessonOfferView $offer): array => self::offer($offer, $locale, $url), $offers),
        ];
    }

    /** @return array<string, string> */
    private static function offer(LessonOfferView $offer, string $locale, string $url): array
    {
        $node = ['@type' => 'Offer', 'name' => $offer->title->in($locale)];
        $description = $offer->description->in($locale);
        if ('' !== $description) {
            $node['description'] = $description;
        }

        return $node + [
            'price' => \sprintf('%d.%02d', intdiv($offer->price->cents, 100), $offer->price->cents % 100),
            'priceCurrency' => Price::CURRENCY,
            'url' => $url,
        ];
    }
}
