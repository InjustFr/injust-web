<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Beats;

use App\Domain\Beats\Deliverable;
use App\Domain\Beats\Exception\InvalidCap;
use App\Domain\Beats\Exception\MissingDeliverables;
use App\Domain\Beats\Exception\MissingTierPrice;
use App\Domain\Beats\Exception\UnnamedTier;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LicenceTierTest extends TestCase
{
    public function testATierKeepsItsDetailsWithDeliverablesInOrderOnce(): void
    {
        $tier = LicenceTier::add(new LicenceTierDetails(new LocalizedText('Premium'), new Price(4900), new LocalizedText('WAV', 'WAV'), [Deliverable::Stems, Deliverable::Mp3, Deliverable::Stems], true, 1, 2, 3), 2);

        $details = $tier->details();
        self::assertEquals([new LocalizedText('Premium'), new Price(4900), new LocalizedText('WAV', 'WAV'), [Deliverable::Mp3, Deliverable::Stems], true, 1, 2, 3, 2], [$details->name, $details->price, $details->summary, $details->deliverables, $details->exclusive, $details->streamCap, $details->copyCap, $details->videoCap, $tier->position()]);
    }

    public function testATierIsEditedAndMoved(): void
    {
        $tier = LicenceTier::add(self::details(), 0);
        $id = $tier->id();

        $tier->edit(new LicenceTierDetails(new LocalizedText('', 'Lease'), new Price(1), new LocalizedText(), [Deliverable::Wav]));
        $tier->moveTo(3);

        $details = $tier->details();
        self::assertEquals([$id, 'Lease', 1, [Deliverable::Wav], false, null, null, null, 3], [$tier->id(), $details->name->en, $details->price->cents, $details->deliverables, $details->exclusive, $details->streamCap, $details->copyCap, $details->videoCap, $tier->position()]);
    }

    public function testATierNeedsAName(): void
    {
        $this->expectException(UnnamedTier::class);

        new LicenceTierDetails(new LocalizedText(' ', ''), new Price(100), new LocalizedText(), [Deliverable::Mp3]);
    }

    public function testATierNeedsAPrice(): void
    {
        $this->expectException(MissingTierPrice::class);

        new LicenceTierDetails(new LocalizedText('Basic'), new Price(0), new LocalizedText(), [Deliverable::Mp3]);
    }

    public function testATierNeedsADeliverable(): void
    {
        $this->expectException(MissingDeliverables::class);

        new LicenceTierDetails(new LocalizedText('Basic'), new Price(100), new LocalizedText(), []);
    }

    /** @param array{?int, ?int, ?int} $caps */
    #[DataProvider('invalidCaps')]
    public function testACapIsAtLeastOne(array $caps): void
    {
        $this->expectException(InvalidCap::class);

        new LicenceTierDetails(new LocalizedText('Basic'), new Price(100), new LocalizedText(), [Deliverable::Mp3], false, ...$caps);
    }

    /** @return iterable<string, array{array{?int, ?int, ?int}}> */
    public static function invalidCaps(): iterable
    {
        yield 'streams' => [[0, null, null]];
        yield 'copies' => [[null, 0, null]];
        yield 'videos' => [[null, null, -1]];
    }

    private static function details(): LicenceTierDetails
    {
        return new LicenceTierDetails(new LocalizedText('Basique', 'Basic'), new Price(2900), new LocalizedText(), [Deliverable::Mp3]);
    }
}
