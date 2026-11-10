<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\Downloads;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesQueries;
use App\Application\Shared\PrivateFileStorage;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\MusicalKey;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;

final class DownloadsTest extends TestCase
{
    public function testOnlyAnExistingBeatOrderHasDownloads(): void
    {
        $beat = OrderView::of(SalesExamples::order(SalesExamples::beat()));
        $lesson = OrderView::of(SalesExamples::order());
        $sales = self::createStub(SalesQueries::class);
        $sales->method('order')->willReturnCallback(static fn (string $id): ?OrderView => [$beat->id => $beat, $lesson->id => $lesson][$id] ?? null);
        $downloads = new Downloads($sales, self::createStub(BeatRepository::class), self::createStub(PrivateFileStorage::class));

        self::assertSame($beat, $downloads->order($beat->id));
        self::assertNull($downloads->order($lesson->id));
        self::assertNull($downloads->order('unknown'));
        self::assertNull($downloads->file($lesson, Deliverable::Mp3));
        self::assertNull($downloads->file($beat, Deliverable::Stems));
    }

    public function testADeletedBeatOrAFileNoLongerOnTheBeatNeverReachesTheStorage(): void
    {
        $order = OrderView::of(SalesExamples::order(SalesExamples::beat()));
        $beat = Beat::add(new BeatDetails('Riot', 'riot', 90, MusicalKey::AMinor, [], true), 0);
        $beat->useFile(Deliverable::Mp3, new BeatFile('01JCMP3000000000000000000A', 'a.mp3', 1, 'audio/mpeg'));
        $beats = self::createStub(BeatRepository::class);
        $beats->method('find')->willReturnOnConsecutiveCalls(null, $beat);
        $storage = $this->createMock(PrivateFileStorage::class);
        $storage->expects($this->never())->method('path');
        $downloads = new Downloads(self::createStub(SalesQueries::class), $beats, $storage);

        self::assertNull($downloads->file($order, Deliverable::Mp3));
        self::assertNull($downloads->file($order, Deliverable::Wav));
    }
}
