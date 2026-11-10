<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Contact;

use App\Application\Contact\ContactOffers;
use App\Application\Lessons\LessonOfferView;
use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Application\Production\ProductionServiceView;
use App\Domain\Contact\ContactTopic;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use PHPUnit\Framework\TestCase;

final class ContactOffersTest extends TestCase
{
    private LessonOfferView $lesson;

    private ProductionServiceView $service;

    private ContactOffers $offers;

    protected function setUp(): void
    {
        $this->lesson = LessonOfferView::of(LessonOffer::add(new LessonOfferDetails(new LocalizedText('Cours', 'Lesson'), new LocalizedText(), OfferKind::Single, 1, LessonFormat::Online, 60, new Price(4000)), 0));
        $this->service = ProductionServiceView::of(ProductionService::add(new ProductionServiceDetails(new LocalizedText('Mixage', 'Mixing'), new LocalizedText(), ServiceKind::Mix, Pricing::Fixed, new Price(12000), null, null), 0));
        $lessons = self::createStub(LessonsQueries::class);
        $lessons->method('offer')->willReturnCallback(fn (string $id): ?LessonOfferView => $id === $this->lesson->id ? $this->lesson : null);
        $production = self::createStub(ProductionQueries::class);
        $production->method('service')->willReturnCallback(fn (string $id): ?ProductionServiceView => $id === $this->service->id ? $this->service : null);
        $this->offers = new ContactOffers($lessons, $production);
    }

    public function testALessonsMessageResolvesALessonOfferInTheVisitorLocale(): void
    {
        $offer = $this->offers->find(ContactTopic::Lessons, $this->lesson->id, 'en');

        self::assertSame([$this->lesson->id, 'Lesson'], [$offer?->id, $offer?->label]);
        self::assertSame('Cours', $this->offers->find(ContactTopic::Lessons, $this->lesson->id, 'fr')?->label);
    }

    public function testAProductionMessageResolvesAServiceInTheVisitorLocale(): void
    {
        $offer = $this->offers->find(ContactTopic::Production, $this->service->id, 'fr');

        self::assertSame([$this->service->id, 'Mixage'], [$offer?->id, $offer?->label]);
        self::assertSame('Mixing', $this->offers->find(ContactTopic::Production, $this->service->id, 'en')?->label);
    }

    public function testAnOfferOfAnotherTopicIsIgnored(): void
    {
        self::assertNull($this->offers->find(ContactTopic::Lessons, $this->service->id, 'en'));
        self::assertNull($this->offers->find(ContactTopic::Production, $this->lesson->id, 'en'));
        self::assertNull($this->offers->find(ContactTopic::General, $this->lesson->id, 'en'));
        self::assertNull($this->offers->find(ContactTopic::Beats, $this->service->id, 'en'));
    }

    public function testAnUnknownRefResolvesNothing(): void
    {
        self::assertNull($this->offers->find(ContactTopic::Lessons, '', 'en'));
        self::assertNull($this->offers->find(ContactTopic::Production, 'nope', 'en'));
    }
}
