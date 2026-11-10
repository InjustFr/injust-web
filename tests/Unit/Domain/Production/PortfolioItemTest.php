<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Production;

use App\Domain\Production\Exception\InvalidYear;
use App\Domain\Production\Exception\MissingRoles;
use App\Domain\Production\PortfolioItem;
use App\Domain\Production\PortfolioItemDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Shared\Exception\InvalidUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PortfolioItemTest extends TestCase
{
    public function testAnItemKeepsItsDetailsAndPosition(): void
    {
        $item = PortfolioItem::add(new PortfolioItemDetails(' Riot ', ' The Clash ', [ServiceKind::Mix, ServiceKind::Master], 2024, ' https://listen.test/riot '), 2);

        $details = $item->details();
        self::assertSame(['Riot', 'The Clash', [ServiceKind::Mix, ServiceKind::Master], 2024, 'https://listen.test/riot', null, 2], [$details->title, $details->artist, $details->roles, $details->year, $details->url, $item->cover(), $item->position()]);
    }

    public function testAnItemIsEditedAndMoved(): void
    {
        $item = PortfolioItem::add(self::details(), 0);
        $id = $item->id();

        $item->edit(new PortfolioItemDetails('London', 'Joe', [ServiceKind::Recording], null, ''));
        $item->moveTo(5);

        self::assertSame([$id, 'London', [ServiceKind::Recording], null, null, 5], [$item->id(), $item->details()->title, $item->details()->roles, $item->details()->year, $item->details()->url, $item->position()]);
    }

    public function testTheCoverIsReplacedAndRemoved(): void
    {
        $item = PortfolioItem::add(self::details(), 0);

        self::assertNull($item->useCover('A'));
        self::assertSame('A', $item->useCover('B'));
        self::assertSame('B', $item->cover());
        self::assertSame('B', $item->removeCover());
        self::assertNull($item->cover());
        self::assertNull($item->removeCover());
    }

    public function testAnItemNeedsARole(): void
    {
        $this->expectException(MissingRoles::class);

        new PortfolioItemDetails('Riot', 'The Clash', [], null, null);
    }

    #[DataProvider('years')]
    public function testTheYearIsBetween1990And2100(?int $year, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidYear::class);
        }

        self::assertSame($year, self::details($year)->year);
    }

    /** @return iterable<array{?int, bool}> */
    public static function years(): iterable
    {
        yield [null, true];
        yield [1990, true];
        yield [2100, true];
        yield [1989, false];
        yield [2101, false];
    }

    public function testTheYearBoundsAreGivenToTheMessage(): void
    {
        self::assertSame(['min' => 1990, 'max' => 2100], new InvalidYear()->parameters());
    }

    public function testTheListenLinkIsHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        new PortfolioItemDetails('Riot', 'The Clash', [ServiceKind::Mix], null, 'http://listen.test');
    }

    private static function details(?int $year = 2020): PortfolioItemDetails
    {
        return new PortfolioItemDetails('Riot', 'The Clash', [ServiceKind::Production], $year, null);
    }
}
