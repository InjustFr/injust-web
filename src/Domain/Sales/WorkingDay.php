<?php

declare(strict_types=1);

namespace App\Domain\Sales;

final class WorkingDay
{
    private const array WEEKEND = ['6', '7'];
    private const array FIXED_HOLIDAYS = ['01-01', '05-01', '05-08', '07-14', '08-15', '11-01', '11-11', '12-25', '12-26'];
    private const array DAYS_AFTER_EASTER = [-2, 1, 39, 50];

    public static function from(\DateTimeImmutable $day): \DateTimeImmutable
    {
        while (self::isOff($day)) {
            $day = $day->modify('+1 day');
        }

        return $day;
    }

    private static function isOff(\DateTimeImmutable $day): bool
    {
        return \in_array($day->format('N'), self::WEEKEND, true)
            || \in_array($day->format('m-d'), self::FIXED_HOLIDAYS, true)
            || \in_array($day->format('m-d'), self::movableHolidays((int) $day->format('Y')), true);
    }

    /** @return list<string> */
    private static function movableHolidays(int $year): array
    {
        $easter = self::easter($year);

        return array_map(static fn (int $days): string => $easter->modify(\sprintf('%+d days', $days))->format('m-d'), self::DAYS_AFTER_EASTER);
    }

    private static function easter(int $year): \DateTimeImmutable
    {
        $golden = $year % 19;
        $century = intdiv($year, 100);
        $epact = (19 * $golden + $century - intdiv($century, 4) - intdiv($century - intdiv($century + 8, 25) + 1, 3) + 15) % 30;
        $weekday = (32 + 2 * ($century % 4) + 2 * intdiv($year % 100, 4) - $epact - $year % 4) % 7;
        $offset = $epact + $weekday - 7 * intdiv($golden + 11 * $epact + 22 * $weekday, 451) + 114;

        return new \DateTimeImmutable(\sprintf('%d-%02d-%02d', $year, intdiv($offset, 31), $offset % 31 + 1));
    }
}
