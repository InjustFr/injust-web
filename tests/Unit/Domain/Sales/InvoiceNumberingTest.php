<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\FiscalYear;
use App\Domain\Sales\InvoiceSequence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceNumberingTest extends TestCase
{
    public function testNumbersFollowEachOtherWithoutGapsWithinAYear(): void
    {
        $sequence = InvoiceSequence::start(2026);

        self::assertSame(['2026-0001', '2026-0002', '2026-0003'], [$sequence->next(), $sequence->next(), $sequence->next()]);
        self::assertSame([2026, 3], [$sequence->year(), $sequence->last()]);
    }

    public function testEachYearStartsAgainAtOne(): void
    {
        $previous = InvoiceSequence::start(2026);
        $previous->next();

        self::assertSame('2027-0001', InvoiceSequence::start(2027)->next());
    }

    /** @return iterable<string, array{string, int}> */
    public static function instants(): iterable
    {
        yield 'last second of 2026 in Paris' => ['2026-12-31 22:59:59 UTC', 2026];
        yield 'New Year in Paris while still 2026 in London' => ['2026-12-31 23:00:00 UTC', 2027];
        yield 'Paris local time' => ['2027-01-01 00:00:00 Europe/Paris', 2027];
        yield 'New York evening of 31 December' => ['2026-12-31 19:30:00 America/New_York', 2027];
        yield 'mid-year' => ['2026-07-14 12:00:00 UTC', 2026];
    }

    #[DataProvider('instants')]
    public function testTheYearOfIssueIsTheCalendarYearInParis(string $instant, int $year): void
    {
        self::assertSame($year, FiscalYear::of(new \DateTimeImmutable($instant)));
    }

    public function testAYearRunsFromParisMidnightToParisMidnight(): void
    {
        [$from, $until] = FiscalYear::bounds(2027);

        self::assertSame(['2026-12-31T23:00:00+00:00', '2027-12-31T23:00:00+00:00'], [$from->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM), $until->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM)]);
    }
}
