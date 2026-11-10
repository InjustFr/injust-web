<?php

declare(strict_types=1);

namespace App\Domain\Sales;

final class FiscalYear
{
    public const string TIMEZONE = 'Europe/Paris';

    public static function of(\DateTimeImmutable $at): int
    {
        return (int) $at->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y');
    }

    /** @return array{\DateTimeImmutable, \DateTimeImmutable} */
    public static function bounds(int $year): array
    {
        $from = new \DateTimeImmutable(\sprintf('%d-01-01 00:00:00', $year), new \DateTimeZone(self::TIMEZONE));

        return [$from, $from->modify('+1 year')];
    }
}
