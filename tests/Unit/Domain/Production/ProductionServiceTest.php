<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Production;

use App\Domain\Production\Exception\InvalidDeliveryDays;
use App\Domain\Production\Exception\InvalidRevisions;
use App\Domain\Production\Exception\MissingFixedPrice;
use App\Domain\Production\Exception\UntitledService;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionPage;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductionServiceTest extends TestCase
{
    public function testAServiceKeepsItsDetailsAndPosition(): void
    {
        $service = ProductionService::add(self::details(deliveryDays: 7, revisions: 2), 3);

        $details = $service->details();
        self::assertSame(['Mix', 'Mixage', ServiceKind::MixMaster, Pricing::Fixed, 15000, 7, 2, 3], [$details->title->en, $details->description->fr, $details->kind, $details->pricing, $details->price->cents, $details->deliveryDays, $details->revisions, $service->position()]);
    }

    public function testAServiceIsEditedAndMoved(): void
    {
        $service = ProductionService::add(self::details(), 0);
        $id = $service->id();

        $service->edit(self::details(Pricing::Quote, 0));
        $service->moveTo(4);

        self::assertSame([$id, Pricing::Quote, 0, null, null, 4], [$service->id(), $service->details()->pricing, $service->details()->price->cents, $service->details()->deliveryDays, $service->details()->revisions, $service->position()]);
    }

    public function testAServiceNeedsATitleInOneLanguage(): void
    {
        self::assertSame('Mastering', new ProductionServiceDetails(new LocalizedText('', 'Mastering'), new LocalizedText(), ServiceKind::Master, Pricing::Quote, new Price(0))->title->in('fr'));

        $this->expectException(UntitledService::class);

        new ProductionServiceDetails(new LocalizedText(' ', ''), new LocalizedText(), ServiceKind::Master, Pricing::Quote, new Price(0));
    }

    public function testAFixedPriceIsNeverZero(): void
    {
        self::assertSame(1, self::details(Pricing::Fixed, 1)->price->cents);
        self::assertSame(0, self::details(Pricing::Quote, 0)->price->cents);

        $this->expectException(MissingFixedPrice::class);

        self::details(Pricing::Fixed, 0);
    }

    #[DataProvider('deliveryDays')]
    public function testTheDeliveryTakesOneToOneHundredTwentyDays(?int $days, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidDeliveryDays::class);
        }

        self::assertSame($days, self::details(deliveryDays: $days)->deliveryDays);
    }

    /** @return iterable<array{?int, bool}> */
    public static function deliveryDays(): iterable
    {
        yield [null, true];
        yield [1, true];
        yield [120, true];
        yield [0, false];
        yield [121, false];
    }

    #[DataProvider('revisions')]
    public function testTheRevisionsGoFromZeroToTen(?int $revisions, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidRevisions::class);
        }

        self::assertSame($revisions, self::details(revisions: $revisions)->revisions);
    }

    /** @return iterable<array{?int, bool}> */
    public static function revisions(): iterable
    {
        yield [null, true];
        yield [0, true];
        yield [10, true];
        yield [-1, false];
        yield [11, false];
    }

    public function testTheBoundsAreGivenToTheMessage(): void
    {
        self::assertSame([['min' => 1, 'max' => 120], ['min' => 0, 'max' => 10]], [new InvalidDeliveryDays()->parameters(), new InvalidRevisions()->parameters()]);
    }

    public function testThePageTextsStartEmptyAndAreEdited(): void
    {
        $page = ProductionPage::initial();
        self::assertSame([true, true, true], [$page->intro()->isEmpty(), $page->process()->isEmpty(), $page->faq()->isEmpty()]);

        $page->edit(new LocalizedText('Salut'), new LocalizedText('', 'Steps'), new LocalizedText('Q'));

        self::assertSame(['Salut', 'Steps', 'Q'], [$page->intro()->fr, $page->process()->en, $page->faq()->fr]);
    }

    private static function details(Pricing $pricing = Pricing::Fixed, int $cents = 15000, ?int $deliveryDays = null, ?int $revisions = null): ProductionServiceDetails
    {
        return new ProductionServiceDetails(new LocalizedText('Mixage fr', 'Mix'), new LocalizedText('Mixage', 'Mixing'), ServiceKind::MixMaster, $pricing, new Price($cents), $deliveryDays, $revisions);
    }
}
