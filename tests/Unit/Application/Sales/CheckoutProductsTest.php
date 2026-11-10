<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\StoredBeatsQueries;
use App\Application\Lessons\LessonOfferView;
use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Application\Production\ProductionServiceView;
use App\Application\Sales\CheckoutProducts;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\BeatsPageRepository;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Beats\LicenceTierRepository;
use App\Domain\Beats\MusicalKey;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Sales\LicensedBeat;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

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

    public function testABeatIsSoldAsItsTierWithTheTierPriceAndALicenceSnapshot(): void
    {
        $catalogue = self::catalogue();
        $beat = $catalogue['beat'];

        $product = self::products(null, null, $catalogue['queries'])->find(ProductType::Beat, $beat->id()->toBase32(), 'fr', $catalogue['premium']->id()->toBase32());

        self::assertNotNull($product);
        self::assertSame([ProductType::Beat, $beat->id()->toBase32(), 'Riot — Premium FR', 5900, null], [$product->type, $product->id, $product->label, $product->price->cents, $product->format]);
        self::assertEquals(new LicensedBeat($beat->id()->toBase32(), 'Riot', 'riot', $catalogue['premium']->id()->toBase32(), new LocalizedText('Premium FR', 'Premium'), false, [Deliverable::Mp3, Deliverable::Wav], 100000, null, 1), $product->beat);
        $basic = self::products(null, null, $catalogue['queries'])->find(ProductType::Beat, $beat->id()->toBase32(), 'en', $catalogue['basic']->id()->toBase32());
        self::assertNotNull($basic);
        self::assertSame(['Riot — Basic', 2900], [$basic->label, $basic->price->cents]);
    }

    public function testABeatIsNotSoldForATierWhoseFileIsMissing(): void
    {
        $catalogue = self::catalogue();

        self::assertNull(self::products(null, null, $catalogue['queries'])->find(ProductType::Beat, $catalogue['beat']->id()->toBase32(), 'en', $catalogue['exclusive']->id()->toBase32()));
    }

    public function testABeatIsNotSoldWithoutAKnownTier(): void
    {
        $catalogue = self::catalogue();
        $id = $catalogue['beat']->id()->toBase32();

        self::assertNull(self::products(null, null, $catalogue['queries'])->find(ProductType::Beat, $id, 'en'));
        self::assertNull(self::products(null, null, $catalogue['queries'])->find(ProductType::Beat, $id, 'en', '01JCUNKNOWN000000000000000'));
    }

    public function testAHiddenOrExclusivelySoldBeatIsNotSold(): void
    {
        $catalogue = self::catalogue();
        $tier = $catalogue['basic']->id()->toBase32();
        $catalogue['sold']->markSoldExclusively(new \DateTimeImmutable('2026-11-01'));

        self::assertNull(self::products(null, null, $catalogue['queries'])->find(ProductType::Beat, $catalogue['hidden']->id()->toBase32(), 'en', $tier));
        self::assertNull(self::products(null, null, $catalogue['queries'])->find(ProductType::Beat, $catalogue['sold']->id()->toBase32(), 'en', $tier));
        self::assertNull(self::products(null, null, $catalogue['queries'])->find(ProductType::Beat, 'nope', 'en', $tier));
    }

    public function testATierIdIsIgnoredForOtherProducts(): void
    {
        $offer = self::lesson(6000, LessonFormat::Online);

        self::assertSame('Lesson', self::products($offer)->find(ProductType::Lesson, $offer->id, 'en', 'whatever')?->label);
        self::assertNull(self::products($offer)->find(ProductType::Lesson, $offer->id, 'en')?->beat);
    }

    /** @return array{queries: BeatsQueries, beat: Beat, hidden: Beat, sold: Beat, basic: LicenceTier, premium: LicenceTier, exclusive: LicenceTier} */
    private static function catalogue(): array
    {
        $beats = ['beat' => self::beat('Riot', 'riot', true), 'hidden' => self::beat('Hidden', 'hidden', false), 'sold' => self::beat('Sold', 'sold', true)];
        $tiers = [
            'basic' => LicenceTier::add(new LicenceTierDetails(new LocalizedText('Basic'), new Price(2900), new LocalizedText(), [Deliverable::Mp3]), 0),
            'premium' => LicenceTier::add(new LicenceTierDetails(new LocalizedText('Premium FR', 'Premium'), new Price(5900), new LocalizedText(), [Deliverable::Mp3, Deliverable::Wav], false, 100000, null, 1), 1),
            'exclusive' => LicenceTier::add(new LicenceTierDetails(new LocalizedText('Exclusive'), new Price(49900), new LocalizedText(), [Deliverable::Mp3, Deliverable::Wav, Deliverable::Stems], true), 2),
        ];
        $repository = self::createStub(BeatRepository::class);
        $repository->method('find')->willReturnCallback(static fn (Ulid $id): ?Beat => array_find($beats, static fn (Beat $beat): bool => $beat->id()->equals($id)));
        $tierRepository = self::createStub(LicenceTierRepository::class);
        $tierRepository->method('all')->willReturn(array_values($tiers));

        return ['queries' => new StoredBeatsQueries(self::createStub(BeatsPageRepository::class), $repository, $tierRepository), ...$beats, ...$tiers];
    }

    private static function beat(string $title, string $slug, bool $visible): Beat
    {
        $beat = Beat::add(new BeatDetails($title, $slug, 90, MusicalKey::AMinor, [], $visible), 0);
        $beat->usePreview(new BeatFile('01JCPREV1EW000000000000000', 'preview.mp3', 1, 'audio/mpeg'));
        $beat->useFile(Deliverable::Mp3, new BeatFile('01JCMP3000000000000000000A', 'a.mp3', 1, 'audio/mpeg'));
        $beat->useFile(Deliverable::Wav, new BeatFile('01JCWAV000000000000000000A', 'a.wav', 1, 'audio/wav'));

        return $beat;
    }

    private static function lesson(int $cents, LessonFormat $format): LessonOfferView
    {
        return LessonOfferView::of(LessonOffer::add(new LessonOfferDetails(new LocalizedText('Cours', 'Lesson'), new LocalizedText(), OfferKind::Single, 1, $format, 60, new Price($cents)), 0));
    }

    private static function products(?LessonOfferView $offer, ?ProductionServiceView $service = null, ?BeatsQueries $beats = null): CheckoutProducts
    {
        $lessons = self::createStub(LessonsQueries::class);
        $lessons->method('offer')->willReturn($offer);
        $production = self::createStub(ProductionQueries::class);
        $production->method('service')->willReturn($service);

        return new CheckoutProducts($lessons, $production, $beats ?? self::createStub(BeatsQueries::class));
    }
}
