<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\Exception\NegativePrice;
use App\Domain\Shared\Price;
use PHPUnit\Framework\TestCase;

final class PriceTest extends TestCase
{
    public function testAPriceIsAnAmountOfEuroCents(): void
    {
        $price = new Price(4500);

        self::assertSame([4500, 'EUR'], [$price->cents, Price::CURRENCY]);
    }

    public function testAFreePriceIsAllowed(): void
    {
        self::assertSame(0, new Price(0)->cents);
    }

    public function testANegativePriceIsRefused(): void
    {
        try {
            new Price(-1);
            self::fail('A negative price was accepted.');
        } catch (NegativePrice $refused) {
            self::assertSame(['shared.negative_price', ['cents' => -1]], [$refused->getMessage(), $refused->parameters()]);
        }
    }
}
