<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Presentation\Web\Site\PriceFormat;
use PHPUnit\Framework\TestCase;

final class PriceFormatTest extends TestCase
{
    public function testPricesDropTheCentsOnlyWhenThereAreNone(): void
    {
        $prices = new PriceFormat();

        self::assertSame('€45', $prices->format(4500, 'en'));
        self::assertSame('€45.50', $prices->format(4550, 'en'));
        self::assertSame('€0.01', $prices->format(1, 'en'));
        self::assertSame('€0', $prices->format(0, 'en'));
        self::assertSame("1\u{202f}045,05\u{a0}€", $prices->format(104505, 'fr'));
    }
}
