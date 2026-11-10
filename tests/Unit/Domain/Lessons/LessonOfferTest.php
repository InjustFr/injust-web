<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Lessons;

use App\Domain\Lessons\Exception\InvalidDuration;
use App\Domain\Lessons\Exception\InvalidSessions;
use App\Domain\Lessons\Exception\UntitledOffer;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LessonOfferTest extends TestCase
{
    public function testAnOfferKeepsItsDetailsAndPosition(): void
    {
        $offer = LessonOffer::add(self::details(OfferKind::Pack, 5, 60), 3);

        $details = $offer->details();
        self::assertSame(['Pack', 'Cinq cours', OfferKind::Pack, 5, LessonFormat::Online, 60, 15000, 3], [$details->title->en, $details->description->fr, $details->kind, $details->sessions, $details->format, $details->minutes, $details->price->cents, $offer->position()]);
    }

    public function testAnOfferIsEditedAndMoved(): void
    {
        $offer = LessonOffer::add(self::details(), 0);
        $id = $offer->id();

        $offer->edit(self::details(OfferKind::Pack, 2, 90));
        $offer->moveTo(4);

        self::assertSame([$id, OfferKind::Pack, 2, 90, 4], [$offer->id(), $offer->details()->kind, $offer->details()->sessions, $offer->details()->minutes, $offer->position()]);
    }

    #[DataProvider('sessions')]
    public function testTheSessionsMatchTheKind(OfferKind $kind, int $sessions, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidSessions::class);
        }

        self::assertSame($sessions, self::details($kind, $sessions)->sessions);
    }

    /** @return iterable<array{OfferKind, int, bool}> */
    public static function sessions(): iterable
    {
        yield [OfferKind::Single, 1, true];
        yield [OfferKind::Single, 2, false];
        yield [OfferKind::Single, 0, false];
        yield [OfferKind::Pack, 2, true];
        yield [OfferKind::Pack, 10, true];
        yield [OfferKind::Pack, 1, false];
    }

    #[DataProvider('durations')]
    public function testTheDurationIsAQuarterHourMultipleBetweenFifteenMinutesAndFourHours(int $minutes, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidDuration::class);
        }

        self::assertSame($minutes, self::details(minutes: $minutes)->minutes);
    }

    /** @return iterable<array{int, bool}> */
    public static function durations(): iterable
    {
        yield [15, true];
        yield [240, true];
        yield [45, true];
        yield [0, false];
        yield [14, false];
        yield [16, false];
        yield [255, false];
        yield [250, false];
    }

    public function testAnOfferNeedsATitleInOneLanguage(): void
    {
        self::assertSame('Cours', new LessonOfferDetails(new LocalizedText('Cours'), new LocalizedText(), OfferKind::Single, 1, LessonFormat::AtHome, 60, new Price(0))->title->in('en'));

        $this->expectException(UntitledOffer::class);

        new LessonOfferDetails(new LocalizedText(' ', ''), new LocalizedText(), OfferKind::Single, 1, LessonFormat::AtHome, 60, new Price(0));
    }

    private static function details(OfferKind $kind = OfferKind::Single, int $sessions = 1, int $minutes = 60): LessonOfferDetails
    {
        return new LessonOfferDetails(new LocalizedText('Pack fr', 'Pack'), new LocalizedText('Cinq cours', 'Five lessons'), $kind, $sessions, LessonFormat::Online, $minutes, new Price(15000));
    }
}
