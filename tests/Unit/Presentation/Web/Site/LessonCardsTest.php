<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Lessons\LessonOfferView;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Presentation\Web\Site\LessonCards;
use App\Presentation\Web\Site\PriceFormat;
use PHPUnit\Framework\TestCase;

final class LessonCardsTest extends TestCase
{
    public function testOffersAreGroupedByFormatInTheFormatOrderKeepingTheirOwnOrder(): void
    {
        $online = self::offer('Pack en ligne', 'Online pack', OfferKind::Pack, 4, LessonFormat::Online, 25001);
        $home = self::offer('Chez toi', 'At your place', OfferKind::Single, 1, LessonFormat::AtHome, 5000);
        $mine = self::offer('Chez moi', 'At mine', OfferKind::Single, 1, LessonFormat::AtTeacher, 4550);
        $homePack = self::offer('Pack maison', 'Home pack', OfferKind::Pack, 3, LessonFormat::AtHome, 20000);

        $groups = new LessonCards(new PriceFormat())->groups([$online, $home, $mine, $homePack], 'en');

        self::assertSame([
            ['at-home', ['At your place', 'Home pack']],
            ['at-teacher', ['At mine']],
            ['online', ['Online pack']],
        ], array_map(static fn (array $group): array => [$group['format'], array_column($group['offers'], 'title')], $groups));
        self::assertSame([null, '€66.67'], array_column($groups[0]['offers'], 'per_lesson'));
        self::assertSame('€62.50', $groups[2]['offers'][0]['per_lesson']);
    }

    public function testACardCarriesTheOfferInTheVisitorLanguage(): void
    {
        $offer = self::offer('Pack en ligne', 'Online pack', OfferKind::Pack, 4, LessonFormat::Online, 25001);

        $groups = new LessonCards(new PriceFormat())->groups([$offer], 'fr');

        self::assertSame([[
            'format' => 'online',
            'offers' => [[
                'id' => $offer->id,
                'title' => 'Pack en ligne',
                'description' => 'Quatre séances.',
                'kind' => 'pack',
                'sessions' => 4,
                'minutes' => 45,
                'price' => "250,01\u{a0}€",
                'bookable' => true,
                'per_lesson' => "62,50\u{a0}€",
            ]],
        ]], $groups);
    }

    public function testOnlyAPaidOfferCanBeBookedOnline(): void
    {
        $groups = new LessonCards(new PriceFormat())->groups([
            self::offer('Essai', 'Trial', OfferKind::Single, 1, LessonFormat::Online, 0),
            self::offer('Cours', 'Lesson', OfferKind::Single, 1, LessonFormat::Online, 1),
        ], 'en');

        self::assertSame([false, true], array_column($groups[0]['offers'], 'bookable'));
    }

    public function testNoOfferMeansNoGroup(): void
    {
        self::assertSame([], new LessonCards(new PriceFormat())->groups([], 'en'));
    }

    private static function offer(string $fr, string $en, OfferKind $kind, int $sessions, LessonFormat $format, int $cents): LessonOfferView
    {
        return LessonOfferView::of(LessonOffer::add(new LessonOfferDetails(new LocalizedText($fr, $en), new LocalizedText('Quatre séances.', 'Four sessions.'), $kind, $sessions, $format, 45, new Price($cents)), 0));
    }
}
