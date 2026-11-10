<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Production\ProductionServiceView;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Presentation\Web\Site\ProductionStructuredData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Translation\IdentityTranslator;

final class ProductionStructuredDataTest extends TestCase
{
    public function testFixedServicesHaveAPriceAndQuotesAMinimumPrice(): void
    {
        $services = [
            self::service(new LocalizedText('Mixage', 'Mix'), new LocalizedText('Stems.', 'Stems in.'), Pricing::Fixed, 4550),
            self::service(new LocalizedText('Production'), new LocalizedText(), Pricing::Quote, 8005),
            self::service(new LocalizedText('Prise de son'), new LocalizedText(), Pricing::Quote, 0),
            self::service(new LocalizedText('Master'), new LocalizedText(), Pricing::Fixed, 5),
        ];

        $service = new ProductionStructuredData(self::urls(), new IdentityTranslator())->service($services, 'fr');

        self::assertSame([
            '@type' => 'Service',
            '@id' => 'https://injust.test/production#service',
            'name' => 'site.production.service',
            'serviceType' => 'site.production.service',
            'url' => 'https://injust.test/production',
            'provider' => ['@id' => 'https://injust.test/home#band'],
            'offers' => [
                ['@type' => 'Offer', 'name' => 'Mixage', 'description' => 'Stems.', 'url' => 'https://injust.test/production', 'price' => '45.50', 'priceCurrency' => 'EUR'],
                ['@type' => 'Offer', 'name' => 'Production', 'url' => 'https://injust.test/production', 'priceSpecification' => ['@type' => 'PriceSpecification', 'minPrice' => '80.05', 'priceCurrency' => 'EUR']],
                ['@type' => 'Offer', 'name' => 'Prise de son', 'url' => 'https://injust.test/production'],
                ['@type' => 'Offer', 'name' => 'Master', 'url' => 'https://injust.test/production', 'price' => '0.05', 'priceCurrency' => 'EUR'],
            ],
        ], $service);
    }

    private static function service(LocalizedText $title, LocalizedText $description, Pricing $pricing, int $cents): ProductionServiceView
    {
        return ProductionServiceView::of(ProductionService::add(new ProductionServiceDetails($title, $description, ServiceKind::Mix, $pricing, new Price($cents)), 0));
    }

    private static function urls(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            /** @param array<string, mixed> $parameters */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return self::ABSOLUTE_URL === $referenceType && [] === $parameters ? 'https://injust.test/'.$name : 'wrong';
            }
        };
    }
}
