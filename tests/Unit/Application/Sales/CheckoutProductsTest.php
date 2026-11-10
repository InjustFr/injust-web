<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Lessons\LessonOfferView;
use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Application\Production\ProductionServiceView;
use App\Application\Sales\CheckoutProducts;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use PHPUnit\Framework\TestCase;

final class CheckoutProductsTest extends TestCase
{
    public function testALessonOfferIsSoldWithItsFormatInTheBuyerLocale(): void
    {
        $offer = self::lesson(6000, LessonFormat::AtHome);

        self::assertEquals(new Purchasable(ProductType::Lesson, $offer->id, 'Cours', new Price(6000), 'at-home'), self::products($offer)->find(ProductType::Lesson, $offer->id, 'fr'));
        self::assertSame('Lesson', self::products($offer)->find(ProductType::Lesson, $offer->id, 'en')?->label);
    }

    public function testAFreeLessonIsNotSoldOnline(): void
    {
        $offer = self::lesson(0, LessonFormat::Online);

        self::assertNull(self::products($offer)->find(ProductType::Lesson, $offer->id, 'fr'));
    }

    public function testAFixedPriceServiceIsSoldWithoutFormat(): void
    {
        $service = ProductionServiceView::of(ProductionService::add(new ProductionServiceDetails(new LocalizedText('Mixage', 'Mixing'), new LocalizedText(), ServiceKind::Mix, Pricing::Fixed, new Price(1)), 0));

        self::assertEquals(new Purchasable(ProductType::Production, $service->id, 'Mixing', new Price(1)), self::products(null, $service)->find(ProductType::Production, $service->id, 'en'));
    }

    public function testAnUnknownServiceIsNotSold(): void
    {
        self::assertNull(self::products(null)->find(ProductType::Production, 'nope', 'en'));
    }

    public function testABeatIsNotSoldYet(): void
    {
        $offer = self::lesson(6000, LessonFormat::Online);

        self::assertNull(self::products($offer)->find(ProductType::Beat, $offer->id, 'fr'));
    }

    private static function lesson(int $cents, LessonFormat $format): LessonOfferView
    {
        return LessonOfferView::of(LessonOffer::add(new LessonOfferDetails(new LocalizedText('Cours', 'Lesson'), new LocalizedText(), OfferKind::Single, 1, $format, 60, new Price($cents)), 0));
    }

    private static function products(?LessonOfferView $offer, ?ProductionServiceView $service = null): CheckoutProducts
    {
        $lessons = self::createStub(LessonsQueries::class);
        $lessons->method('offer')->willReturn($offer);
        $production = self::createStub(ProductionQueries::class);
        $production->method('service')->willReturn($service);

        return new CheckoutProducts($lessons, $production);
    }
}
