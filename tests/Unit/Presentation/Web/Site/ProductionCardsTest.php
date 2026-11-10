<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Production\ProductionServiceView;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Presentation\Web\Site\PriceFormat;
use App\Presentation\Web\Site\ProductionCards;
use PHPUnit\Framework\TestCase;

final class ProductionCardsTest extends TestCase
{
    public function testACardCarriesTheServiceInTheVisitorLanguage(): void
    {
        $service = self::service(new LocalizedText('Mixage', 'Mix'), new LocalizedText('Un **mix**.', 'A mix.'), Pricing::Fixed, 12050, 5, 0);

        self::assertSame([[
            'id' => $service->id,
            'kind' => 'mix-master',
            'title' => 'Mixage',
            'description' => "<p>Un <strong>mix</strong>.</p>\n",
            'pricing' => 'fixed',
            'price' => "120,50\u{a0}€",
            'delivery_days' => 5,
            'revisions' => 0,
        ]], self::cards()->cards([$service], 'fr'));
    }

    public function testAQuoteWithoutAmountHasNoPriceAndABlankDescriptionNoHtml(): void
    {
        $quote = self::service(new LocalizedText('', 'Recording'), new LocalizedText(), Pricing::Quote, 0, null, null);
        $from = self::service(new LocalizedText('Production'), new LocalizedText(), Pricing::Quote, 8000, null, null);

        $cards = self::cards()->cards([$quote, $from], 'en');

        self::assertSame(['Recording', 'Production'], array_column($cards, 'title'));
        self::assertSame([null, '€80'], array_column($cards, 'price'));
        self::assertSame(['', ''], array_column($cards, 'description'));
        self::assertSame(['quote', 'quote'], array_column($cards, 'pricing'));
        self::assertSame([null, null], array_column($cards, 'delivery_days'));
    }

    private static function cards(): ProductionCards
    {
        return new ProductionCards(new PriceFormat(), new LegalMarkdown());
    }

    private static function service(LocalizedText $title, LocalizedText $description, Pricing $pricing, int $cents, ?int $deliveryDays, ?int $revisions): ProductionServiceView
    {
        return ProductionServiceView::of(ProductionService::add(new ProductionServiceDetails($title, $description, ServiceKind::MixMaster, $pricing, new Price($cents), $deliveryDays, $revisions), 0));
    }
}
