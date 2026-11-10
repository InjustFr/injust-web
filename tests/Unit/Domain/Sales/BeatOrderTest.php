<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Application\Sales\OrderView;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\MusicalKey;
use App\Domain\Sales\LicenceSnapshot;
use App\Domain\Sales\LicensedBeat;
use App\Domain\Sales\Order;
use App\Domain\Shared\LocalizedText;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;

final class BeatOrderTest extends TestCase
{
    public function testABeatOrderFreezesTheBeatTheTierAndTheLicenceText(): void
    {
        $order = SalesExamples::order(SalesExamples::beat(4900, true));

        $licence = $order->licence();
        self::assertNotNull($licence);
        self::assertEquals(new LicenceSnapshot(SalesExamples::licensedBeat(true), SalesExamples::licence()), $licence);
        self::assertSame(['01JCBEAT000000000000000000', 'Riot', 'riot', '01JCT1ER000000000000000000', 'Premium FR', 'Premium', true, 10000, null, 1], [
            $licence->beat->beatId,
            $licence->beat->title,
            $licence->beat->slug,
            $licence->beat->tierId,
            $licence->beat->tierName->fr,
            $licence->beat->tierName->en,
            $licence->beat->exclusive,
            $licence->beat->streamCap,
            $licence->beat->copyCap,
            $licence->beat->videoCap,
        ]);
        self::assertSame([Deliverable::Mp3, Deliverable::Wav], $licence->beat->deliverables);
        self::assertSame([2, 'Licence v2', 'Licence EN v2', '2026-11-02T09:00:00+00:00'], [$licence->terms->version, $licence->terms->body->fr, $licence->terms->body->en, $licence->terms->publishedAt->format(\DATE_ATOM)]);
        self::assertNull($order->exclusiveConflictAt());
    }

    public function testTheSnapshotSurvivesItsStorageForm(): void
    {
        $snapshot = new LicenceSnapshot(new LicensedBeat('01JCBEAT000000000000000000', 'Riot', 'riot', 'tier', new LocalizedText('Exclusif', 'Exclusive'), true, [Deliverable::Stems, Deliverable::Mp3], null, 500, 2), SalesExamples::licence(4));

        self::assertEquals($snapshot, LicenceSnapshot::fromArray($snapshot->toArray()));
        self::assertSame(['stems', 'mp3'], $snapshot->toArray()['beat']['deliverables']);
    }

    public function testABeatDeliversOnlyTheFilesOfItsTier(): void
    {
        $beat = SalesExamples::licensedBeat(deliverables: [Deliverable::Mp3]);

        self::assertTrue($beat->delivers(Deliverable::Mp3));
        self::assertFalse($beat->delivers(Deliverable::Wav));
        self::assertFalse($beat->delivers(Deliverable::Stems));
    }

    public function testOtherProductsHaveNoLicence(): void
    {
        self::assertNull(SalesExamples::order(SalesExamples::lesson())->licence());
        self::assertNull(OrderView::of(SalesExamples::order(SalesExamples::production()))->licence);
    }

    public function testAnOtherProductPlacedWithoutLicenceHasNone(): void
    {
        $order = Order::place(SalesExamples::lesson(), 'fr', '', SalesExamples::consents(SalesExamples::lesson()->type), SalesExamples::terms(), new \DateTimeImmutable('2026-11-10 10:00:00'));

        self::assertNull($order->licence());
        self::assertFalse($order->needsConflictNotice());
    }

    public function testSellingAnotherProductLeavesTheBeatAlone(): void
    {
        $beat = Beat::add(new BeatDetails('Riot', 'riot', 90, MusicalKey::AMinor, [], true), 0);
        $order = SalesExamples::paid(SalesExamples::lesson());

        $order->sell($beat, new \DateTimeImmutable('2026-11-10 10:06:00'));

        self::assertNull($beat->soldExclusivelyAt());
        self::assertNull($order->exclusiveConflictAt());
    }

    public function testAConflictNoticeIsNeededOnceTheConflictIsFlaggedUntilItIsSent(): void
    {
        $order = SalesExamples::paid(SalesExamples::beat());
        self::assertFalse($order->needsConflictNotice());

        $order->flagExclusiveConflict(new \DateTimeImmutable('2026-11-10 10:06:00'));
        self::assertTrue($order->needsConflictNotice());

        $order->conflictNoticeSent(new \DateTimeImmutable('2026-11-10 10:07:00'));
        self::assertFalse($order->needsConflictNotice());
    }

    public function testAnExclusiveConflictKeepsItsFirstDateAndIsShownOnTheView(): void
    {
        $order = SalesExamples::paid(SalesExamples::beat());

        $order->flagExclusiveConflict(new \DateTimeImmutable('2026-11-10 10:06:00'));
        $order->flagExclusiveConflict(new \DateTimeImmutable('2026-11-11 10:06:00'));

        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00'), $order->exclusiveConflictAt());
        $view = OrderView::of($order);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00'), $view->exclusiveConflictAt);
        self::assertEquals($order->licence(), $view->licence);
    }
}
