<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Beats;

use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\Exception\InvalidBpm;
use App\Domain\Beats\Exception\InvalidTags;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Music\Exception\InvalidSlug;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BeatTest extends TestCase
{
    public function testABeatKeepsItsDetailsAndStartsHidden(): void
    {
        $beat = Beat::add(new BeatDetails(' Riot Gear ', null, 140, MusicalKey::CSharpMinor, [' Punk ', 'HARD rock']), 3);

        $details = $beat->details();
        self::assertSame(['Riot Gear', 'riot-gear', 140, MusicalKey::CSharpMinor, ['punk', 'hard rock'], false, 3], [$details->title, $details->slug, $details->bpm, $details->key, $details->tags, $details->visible, $beat->position()]);
        self::assertSame([null, null, null], [$beat->cover(), $beat->preview(), $beat->soldExclusivelyAt()]);
        self::assertFalse($beat->isAvailable());
    }

    public function testABeatIsEditedAndMoved(): void
    {
        $beat = Beat::add(self::details(), 0);
        $id = $beat->id();

        $beat->edit(new BeatDetails('Other', ' My Slug! ', 90, MusicalKey::AMinor, [], true));
        $beat->moveTo(4);

        self::assertSame([$id, 'Other', 'my-slug', 90, MusicalKey::AMinor, [], true, 4], [$beat->id(), $beat->details()->title, $beat->details()->slug, $beat->details()->bpm, $beat->details()->key, $beat->details()->tags, $beat->details()->visible, $beat->position()]);
    }

    public function testAnUnsluggableTitleIsRefused(): void
    {
        $this->expectException(InvalidSlug::class);

        new BeatDetails('!!!', '', 120, MusicalKey::CMajor);
    }

    #[DataProvider('bpms')]
    public function testTheTempoStaysBetween40And300(int $bpm, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidBpm::class);
        }

        self::assertSame($bpm, new BeatDetails('Beat', null, $bpm, MusicalKey::CMajor)->bpm);
    }

    /** @return iterable<array{int, bool}> */
    public static function bpms(): iterable
    {
        yield [39, false];
        yield [40, true];
        yield [300, true];
        yield [301, false];
    }

    public function testTagsAreTrimmedLowercasedAndDeduplicated(): void
    {
        self::assertSame(['trap', 'dark'], new BeatDetails('Beat', null, 120, MusicalKey::CMajor, ['Trap', ' trap ', 'DARK', 'dark'])->tags);
        self::assertSame(['éa'], new BeatDetails('Beat', null, 120, MusicalKey::CMajor, ['ÉA'])->tags);
    }

    public function testEightTagsOfTwentyFourCharactersAreAllowed(): void
    {
        $tags = array_map(static fn (int $n): string => str_repeat('a', 23).$n, range(1, 8));

        self::assertSame($tags, new BeatDetails('Beat', null, 120, MusicalKey::CMajor, $tags)->tags);
        self::assertSame([str_repeat('é', 24)], new BeatDetails('Beat', null, 120, MusicalKey::CMajor, [str_repeat('é', 24)])->tags);
    }

    /** @param list<string> $tags */
    #[DataProvider('invalidTags')]
    public function testInvalidTagsAreRefused(array $tags): void
    {
        $this->expectException(InvalidTags::class);

        new BeatDetails('Beat', null, 120, MusicalKey::CMajor, $tags);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidTags(): iterable
    {
        yield 'nine' => [array_map(strval(...), range(1, 9))];
        yield 'blank' => [['ok', '  ']];
        yield 'too long' => [[str_repeat('a', 25)]];
    }

    public function testFilesAreReplacedAndRemoved(): void
    {
        $beat = Beat::add(self::details(), 0);
        $first = new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', 'a.wav', 10, 'audio/wav');
        $second = new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZB', 'b.wav', 20, 'audio/x-wav');

        self::assertNull($beat->useFile(Deliverable::Wav, $first));
        self::assertEquals($first, $beat->useFile(Deliverable::Wav, $second));
        self::assertEquals($second, $beat->file(Deliverable::Wav));
        self::assertSame([true, false, false], [$beat->hasFile(Deliverable::Wav), $beat->hasFile(Deliverable::Mp3), $beat->hasFile(Deliverable::Stems)]);
        self::assertEquals([$second], $beat->files());
        self::assertEquals($second, $beat->removeFile(Deliverable::Wav));
        self::assertNull($beat->removeFile(Deliverable::Wav));
        self::assertNull($beat->file(Deliverable::Wav));
        self::assertSame([], $beat->files());
    }

    public function testAFileNameIsTrimmedAndCut(): void
    {
        self::assertSame(str_repeat('é', 200), new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', ' '.str_repeat('é', 201), 1, 'audio/wav')->originalName);
        self::assertSame('a.wav', new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', " a.wav\n", 1, 'audio/wav')->originalName);
    }

    public function testFilesAreListedInDeliverableOrder(): void
    {
        $beat = Beat::add(self::details(), 0);
        $stems = new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', 'stems.zip', 10, 'application/zip');
        $mp3 = new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZB', 'beat.mp3', 20, 'audio/mpeg');

        $beat->useFile(Deliverable::Stems, $stems);
        $beat->useFile(Deliverable::Mp3, $mp3);

        self::assertEquals([$mp3, $stems], $beat->files());
    }

    public function testThePreviewAndCoverAreReplacedAndRemoved(): void
    {
        $beat = Beat::add(self::details(), 0);
        $preview = new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', 'tag.mp3', 10, 'audio/mpeg');

        self::assertNull($beat->usePreview($preview));
        self::assertEquals($preview, $beat->usePreview($preview));
        self::assertEquals($preview, $beat->preview());
        self::assertEquals($preview, $beat->removePreview());
        self::assertNull($beat->removePreview());
        self::assertNull($beat->useCover('A'));
        self::assertSame('A', $beat->useCover('B'));
        self::assertSame('B', $beat->removeCover());
        self::assertNull($beat->cover());
    }

    public function testAVisibleBeatIsListedOnlyWithAPreviewAndUntilSoldExclusively(): void
    {
        $beat = Beat::add(self::details(visible: true), 0);

        self::assertSame([true, false], [$beat->isAvailable(), $beat->isListed()]);
        $beat->usePreview(new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', 'tag.mp3', 10, 'audio/mpeg'));
        self::assertTrue($beat->isListed());

        $soldAt = new \DateTimeImmutable('2026-11-10 12:00');
        $beat->markSoldExclusively($soldAt);
        $beat->markSoldExclusively(new \DateTimeImmutable('2026-12-01'));

        self::assertSame([$soldAt, false, false], [$beat->soldExclusivelyAt(), $beat->isAvailable(), $beat->isListed()]);
    }

    public function testAHiddenBeatWithAPreviewIsNotListed(): void
    {
        $beat = Beat::add(self::details(), 0);
        $beat->usePreview(new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', 'tag.mp3', 10, 'audio/mpeg'));

        self::assertFalse($beat->isListed());
    }

    public function testABeatIsPurchasableForATierOnlyWithEveryFileOfTheTier(): void
    {
        $beat = Beat::add(self::details(visible: true), 0);
        $tier = self::tier([Deliverable::Mp3, Deliverable::Wav]);
        $beat->useFile(Deliverable::Mp3, new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', 'a.mp3', 1, 'audio/mpeg'));

        self::assertFalse($beat->purchasableAs($tier));
        self::assertTrue($beat->purchasableAs(self::tier([Deliverable::Mp3])));

        $beat->useFile(Deliverable::Wav, new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZB', 'a.wav', 1, 'audio/wav'));
        self::assertTrue($beat->purchasableAs($tier));
        $beat->markSoldExclusively(new \DateTimeImmutable());
        self::assertFalse($beat->purchasableAs($tier));
    }

    public function testAHiddenBeatIsNotPurchasable(): void
    {
        $beat = Beat::add(self::details(), 0);
        $beat->useFile(Deliverable::Mp3, new BeatFile('01HZZZZZZZZZZZZZZZZZZZZZZA', 'a.mp3', 1, 'audio/mpeg'));

        self::assertFalse($beat->purchasableAs(self::tier([Deliverable::Mp3])));
    }

    private static function details(bool $visible = false): BeatDetails
    {
        return new BeatDetails('Riot', null, 140, MusicalKey::AMinor, [], $visible);
    }

    /** @param list<Deliverable> $deliverables */
    private static function tier(array $deliverables): LicenceTier
    {
        return LicenceTier::add(new LicenceTierDetails(new LocalizedText('Basique', 'Basic'), new Price(2900), new LocalizedText(), $deliverables), 0);
    }
}
