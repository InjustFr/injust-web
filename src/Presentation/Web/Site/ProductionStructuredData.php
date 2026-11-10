<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Production\ProductionServiceView;
use App\Domain\Production\Pricing;
use App\Domain\Shared\Price;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ProductionStructuredData
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<ProductionServiceView> $services
     *
     * @return array<string, mixed>
     */
    public function service(array $services, string $locale): array
    {
        $url = $this->urls->generate('production', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $name = $this->translator->trans('site.production.service');

        return [
            '@type' => 'Service',
            '@id' => $url.'#service',
            'name' => $name,
            'serviceType' => $name,
            'url' => $url,
            'provider' => ['@id' => $this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL).'#band'],
            'offers' => array_map(static fn (ProductionServiceView $service): array => self::offer($service, $locale, $url), $services),
        ];
    }

    /** @return array<string, mixed> */
    private static function offer(ProductionServiceView $service, string $locale, string $url): array
    {
        $node = ['@type' => 'Offer', 'name' => $service->title->in($locale)];
        $description = $service->description->in($locale);
        if ('' !== $description) {
            $node['description'] = $description;
        }
        $node['url'] = $url;
        $amount = \sprintf('%d.%02d', intdiv($service->price->cents, 100), $service->price->cents % 100);
        if (Pricing::Fixed->value === $service->pricing) {
            return $node + ['price' => $amount, 'priceCurrency' => Price::CURRENCY];
        }
        if (0 === $service->price->cents) {
            return $node;
        }

        return $node + ['priceSpecification' => ['@type' => 'PriceSpecification', 'minPrice' => $amount, 'priceCurrency' => Price::CURRENCY]];
    }
}
