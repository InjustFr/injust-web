<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\BeatTierView;
use App\Application\Beats\BeatView;
use App\Application\Beats\LicenceTierView;
use App\Application\Lessons\LessonOfferView;
use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Application\Production\ProductionServiceView;
use App\Application\Sales\CheckoutProducts;
use App\Application\Sales\CheckoutRequest;
use App\Application\Sales\StartCheckout\CheckoutStarted;
use App\Application\Sales\StartCheckout\StartCheckout;
use App\Application\Sales\StartCheckout\StartCheckoutHandler;
use App\Application\Settings\LegalDocumentHtml;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
use App\Application\Settings\SettingsView;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Sales\Consent;
use App\Domain\Sales\ConsentKind;
use App\Domain\Sales\Exception\CheckoutClosed;
use App\Domain\Sales\Exception\LicenceChanged;
use App\Domain\Sales\Exception\MissingConsent;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\Exception\ProductUnavailable;
use App\Domain\Sales\Exception\SectionClosed;
use App\Domain\Sales\Exception\TermsChanged;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Terms;
use App\Domain\Settings\LegalDocument;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\Settings;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Tests\Support\Sales\FakeCheckoutUrls;
use App\Tests\Support\Sales\FakeConsentTexts;
use App\Tests\Support\Sales\FakePaymentGateway;
use App\Tests\Support\Sales\InMemoryOrders;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingTransaction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class StartCheckoutTest extends TestCase
{
    private const array SERVICE_CONSENTS = ['terms', 'early-start', 'pro-rata'];
    private const array BEAT_CONSENTS = ['terms', 'digital-waiver', 'licence'];

    private BeatView $beat;

    private LicenceTierView $tier;

    private Settings $settings;

    private LessonOfferView $lesson;

    private ProductionServiceView $fixed;

    private ProductionServiceView $quote;

    private InMemoryOrders $orders;

    private FakePaymentGateway $gateway;

    private Journal $journal;

    private ?LocalizedText $cgvBody = null;

    protected function setUp(): void
    {
        $this->settings = Settings::initial();
        $this->settings->editLegalIdentity(new LegalIdentity('Louis Injust', 'EI', '443061841', '1 rue du Son', 'louis@injust.test', '+33600000000', 'Louis', 'Médiateur', 'https://mediator.test', 'OVH', 'Roubaix', '+33972101007'));
        $this->settings->enableOnlineCheckout();
        $this->settings->switchSections(true, true, true);
        $this->lesson = LessonOfferView::of(LessonOffer::add(new LessonOfferDetails(new LocalizedText('Cours en ligne', 'Online lesson'), new LocalizedText(), OfferKind::Single, 1, LessonFormat::Online, 60, new Price(4500)), 0));
        $this->fixed = ProductionServiceView::of(ProductionService::add(new ProductionServiceDetails(new LocalizedText('Mixage', 'Mixing'), new LocalizedText(), ServiceKind::Mix, Pricing::Fixed, new Price(12000)), 0));
        $this->quote = ProductionServiceView::of(ProductionService::add(new ProductionServiceDetails(new LocalizedText('Production', 'Production'), new LocalizedText(), ServiceKind::Production, Pricing::Quote, new Price(0)), 1));
        $beat = Beat::add(new BeatDetails('Riot', 'riot', 90, MusicalKey::AMinor, [], true), 0);
        $beat->usePreview(new BeatFile('01JCPREV1EW000000000000000', 'preview.mp3', 1, 'audio/mpeg'));
        $beat->useFile(Deliverable::Mp3, new BeatFile('01JCMP3000000000000000000A', 'a.mp3', 1, 'audio/mpeg'));
        $this->beat = BeatView::of($beat);
        $this->tier = LicenceTierView::of(LicenceTier::add(new LicenceTierDetails(new LocalizedText('Premium FR', 'Premium'), new Price(5900), new LocalizedText(), [Deliverable::Mp3]), 0));
        $this->journal = new Journal();
        $this->orders = new InMemoryOrders();
        $this->gateway = new FakePaymentGateway();
    }

    public function testAPendingOrderIsCreatedThenSentToThePaymentPage(): void
    {
        $started = $this->start(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', ' buyer@example.test ', self::SERVICE_CONSENTS, 3));

        $order = $this->onlyOrder();
        self::assertSame([$order->id()->toBase32(), 'https://pay.test/cs_1'], [$started->orderId, $started->url]);
        self::assertSame([OrderStatus::Pending, ProductType::Lesson, $this->lesson->id, 'Online lesson', 'online', 4500, 'en', 'buyer@example.test', 3, 'cs_1'], [$order->status(), $order->productType(), $order->productId(), $order->productLabel(), $order->productFormat(), $order->price()->cents, $order->locale(), $order->email(), $order->cgvVersion(), $order->checkoutSessionId()]);
        self::assertEquals([
            new Consent(ConsentKind::Terms, 'en:terms:v3', new \DateTimeImmutable('2026-11-10 10:00:00')),
            new Consent(ConsentKind::EarlyStart, 'en:early-start:v3', new \DateTimeImmutable('2026-11-10 10:00:00')),
            new Consent(ConsentKind::ProRata, 'en:pro-rata:v3', new \DateTimeImmutable('2026-11-10 10:00:00')),
        ], $order->consents());
        self::assertEquals([new CheckoutRequest(
            $order->id()->toBase32(),
            $order->reference(),
            'Online lesson',
            4500,
            'eur',
            'en',
            'buyer@example.test',
            'https://injust.test/en/checkout/success?order='.$order->id()->toBase32(),
            'https://injust.test/en/checkout/cancel?order='.$order->id()->toBase32(),
        )], $this->gateway->requests);
        self::assertEquals(new Terms(3, new LocalizedText('CGV v3'), new \DateTimeImmutable('2026-11-01 09:00:00')), $order->terms());
        self::assertSame(['commit', 'commit'], $this->journal->entries);
    }

    public function testAFixedPriceServiceIsSoldInTheOrderLocale(): void
    {
        $this->start(new StartCheckout(ProductType::Production, $this->fixed->id, 'fr', '', self::SERVICE_CONSENTS, 5));

        $order = $this->onlyOrder();
        self::assertSame(['Mixage', null, 12000, 5, 'fr:terms:v5'], [$order->productLabel(), $order->productFormat(), $order->price()->cents, $order->cgvVersion(), $order->consents()[0]->text]);
        self::assertNull($this->gateway->requests[0]->email);
    }

    public function testTheAcceptedTermsAreFrozenWithTheSellerIdentityOfTheDay(): void
    {
        $this->cgvBody = new LocalizedText('CGV de {name} ({siren}), {siteUrl}, {updatedAt}, {unknown}', 'Terms of {name}, {updatedAt}');

        $this->start(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', '', self::SERVICE_CONSENTS, 3));

        $body = $this->onlyOrder()->terms()->body;
        self::assertSame(['CGV de Louis Injust (443061841), https://injust.test, 1 novembre 2026, {unknown}', 'Terms of Louis Injust, November 1, 2026'], [$body->fr, $body->en]);
    }

    public function testAnEmptyLanguageOfTheTermsStaysEmpty(): void
    {
        $this->cgvBody = new LocalizedText('Vendeur {name}');

        $this->start(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', '', self::SERVICE_CONSENTS, 3));

        $body = $this->onlyOrder()->terms()->body;
        self::assertSame(['Vendeur Louis Injust', ''], [$body->fr, $body->en]);
    }

    public function testTermsEditedAfterThePageWasShownMustBeReadAgain(): void
    {
        $this->refused(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', '', self::SERVICE_CONSENTS, 2), new TermsChanged());
    }

    public function testNothingIsSoldWhileOnlineCheckoutIsOff(): void
    {
        $this->settings->disableOnlineCheckout();

        $this->refused(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', '', self::SERVICE_CONSENTS, 3), new CheckoutClosed());
    }

    public function testNothingIsSoldFromASectionThatIsOff(): void
    {
        $this->settings->switchSections(false, true, true);

        $this->refused(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', '', self::SERVICE_CONSENTS, 3), new SectionClosed());
    }

    public function testAQuoteServiceIsNeverSold(): void
    {
        $this->refused(new StartCheckout(ProductType::Production, $this->quote->id, 'en', '', self::SERVICE_CONSENTS, 5), new ProductUnavailable());
    }

    public function testAnUnknownProductIsNotSold(): void
    {
        $this->refused(new StartCheckout(ProductType::Lesson, $this->fixed->id, 'en', '', self::SERVICE_CONSENTS, 3), new ProductUnavailable());
    }

    public function testABeatIsOrderedForItsTierWithTheLicenceTextFrozen(): void
    {
        $this->start(new StartCheckout(ProductType::Beat, $this->beat->id, 'fr', '', self::BEAT_CONSENTS, 5, $this->tier->id, 2));

        $order = $this->onlyOrder();
        self::assertSame([ProductType::Beat, $this->beat->id, 'Riot — Premium FR', null, 5900, 5], [$order->productType(), $order->productId(), $order->productLabel(), $order->productFormat(), $order->price()->cents, $order->cgvVersion()]);
        self::assertSame(['fr:terms:v5', 'fr:digital-waiver:v5', 'fr:licence:v2'], array_map(static fn (Consent $consent): string => $consent->text, $order->consents()));
        $licence = $order->licence();
        self::assertNotNull($licence);
        self::assertSame([$this->beat->id, 'Riot', 'riot', $this->tier->id, false, [Deliverable::Mp3]], [$licence->beat->beatId, $licence->beat->title, $licence->beat->slug, $licence->beat->tierId, $licence->beat->exclusive, $licence->beat->deliverables]);
        self::assertEquals(new Terms(2, new LocalizedText('Licence de Louis Injust (443061841)', 'Licence of Louis Injust'), new \DateTimeImmutable('2026-11-01 09:00:00')), $licence->terms);
        self::assertSame('Riot — Premium FR', $this->gateway->requests[0]->label);
    }

    public function testALicenceEditedAfterThePageWasShownMustBeReadAgain(): void
    {
        $this->refused(new StartCheckout(ProductType::Beat, $this->beat->id, 'en', '', self::BEAT_CONSENTS, 5, $this->tier->id, 1), new LicenceChanged());
    }

    public function testABeatIsNotSoldWithoutTheLicenceVersionShown(): void
    {
        $this->refused(new StartCheckout(ProductType::Beat, $this->beat->id, 'en', '', self::BEAT_CONSENTS, 5, $this->tier->id), new LicenceChanged());
    }

    public function testABeatIsNotSoldWithoutATier(): void
    {
        $this->refused(new StartCheckout(ProductType::Beat, $this->beat->id, 'en', '', self::BEAT_CONSENTS, 5), new ProductUnavailable());
    }

    public function testABeatIsNotSoldWhileTheBeatsSectionIsOff(): void
    {
        $this->settings->switchSections(true, true, false);

        $this->refused(new StartCheckout(ProductType::Beat, $this->beat->id, 'en', '', self::BEAT_CONSENTS, 5, $this->tier->id, 2), new SectionClosed());
    }

    public function testABeatNeedsTheLicenceConsent(): void
    {
        $this->refused(new StartCheckout(ProductType::Beat, $this->beat->id, 'en', '', ['terms', 'digital-waiver'], 5, $this->tier->id, 2), new MissingConsent());
    }

    public function testEveryRequiredConsentMustBeTicked(): void
    {
        $this->refused(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', '', ['terms', 'early-start', 'licence', 'nonsense'], 3), new MissingConsent());
    }

    public function testExtraConsentsAreIgnored(): void
    {
        $this->start(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', '', ['licence', ...self::SERVICE_CONSENTS, 'terms', 'nonsense'], 3));

        self::assertSame([ConsentKind::Terms, ConsentKind::EarlyStart, ConsentKind::ProRata], array_map(static fn (Consent $consent): ConsentKind => $consent->kind, $this->onlyOrder()->consents()));
    }

    public function testTheOrderExpiresWhenThePaymentPageCannotBeOpened(): void
    {
        $this->gateway->down = true;

        try {
            $this->start(new StartCheckout(ProductType::Lesson, $this->lesson->id, 'en', '', self::SERVICE_CONSENTS, 3));
            self::fail('The checkout started without a payment page.');
        } catch (PaymentUnavailable) {
        }

        self::assertSame(OrderStatus::Expired, $this->onlyOrder()->status());
        self::assertNull($this->onlyOrder()->checkoutSessionId());
        self::assertSame(['commit', 'commit'], $this->journal->entries);
    }

    private function refused(StartCheckout $command, \Throwable $expected): void
    {
        try {
            $this->start($command);
            self::fail('The checkout started.');
        } catch (\Throwable $refusal) {
            self::assertEquals($expected, $refusal);
        }

        self::assertSame([], $this->orders->orders);
        self::assertSame([], $this->gateway->requests);
        self::assertSame([], $this->journal->entries);
    }

    private function start(StartCheckout $command): CheckoutStarted
    {
        $lessons = self::createStub(LessonsQueries::class);
        $lessons->method('offer')->willReturnCallback(fn (string $id): ?LessonOfferView => $id === $this->lesson->id ? $this->lesson : null);
        $production = self::createStub(ProductionQueries::class);
        $production->method('service')->willReturnCallback(fn (string $id): ?ProductionServiceView => [$this->fixed->id => $this->fixed, $this->quote->id => $this->quote][$id] ?? null);
        $beats = self::createStub(BeatsQueries::class);
        $beats->method('beat')->willReturnCallback(fn (string $id): ?BeatView => $id === $this->beat->id ? $this->beat : null);
        $beats->method('tiersFor')->willReturnCallback(fn (string $id): array => $id === $this->beat->id ? [new BeatTierView($this->tier, true)] : []);
        $settings = self::createStub(SettingsQueries::class);
        $settings->method('current')->willReturnCallback(fn (): SettingsView => SettingsView::of($this->settings));
        $settings->method('legalDocument')->willReturnCallback(function (LegalKind $kind): LegalDocumentView {
            $document = LegalDocument::draft($kind, new LocalizedText('CGV'), new \DateTimeImmutable('2026-10-01 09:00:00'));
            if (LegalKind::Licence === $kind) {
                $document->edit(new LocalizedText('Licence de {name} ({siren})', 'Licence of {name}'), new \DateTimeImmutable('2026-11-01 09:00:00'));

                return LegalDocumentView::of($document);
            }
            foreach (range(2, LegalKind::CgvLessons === $kind ? 3 : 5) as $version) {
                $document->edit($this->cgvBody ?? new LocalizedText('CGV v'.$version), new \DateTimeImmutable('2026-11-01 09:00:00'));
            }

            return LegalDocumentView::of($document);
        });

        return new StartCheckoutHandler(
            $settings,
            new CheckoutProducts($lessons, $production, $beats),
            new FakeConsentTexts(),
            new FakeCheckoutUrls(),
            $this->orders,
            $this->gateway,
            new MockClock('2026-11-10 10:00:00'),
            new RecordingTransaction($this->journal),
            new LegalDocumentHtml(new LegalMarkdown()),
            'https://injust.test',
        )($command);
    }

    private function onlyOrder(): Order
    {
        self::assertCount(1, $this->orders->orders);

        return array_values($this->orders->orders)[0];
    }
}
