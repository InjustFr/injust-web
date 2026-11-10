<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Exception\InvalidUpc;
use App\Domain\Music\Upc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpcTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function valid(): iterable
    {
        yield 'UPC-A of the single' => ['701518909440', '701518909440'];
        yield 'UPC-A starting with zero' => ['012345678905', '012345678905'];
        yield 'EAN-13' => ['4006381333931', '4006381333931'];
        yield 'surrounding spaces' => ['  036000291452 ', '036000291452'];
    }

    #[DataProvider('valid')]
    public function testAValidBarcodeIsKeptAsDigits(string $input, string $expected): void
    {
        self::assertSame($expected, Upc::orNull($input));
    }

    public function testBlankMeansNoUpc(): void
    {
        self::assertNull(Upc::orNull(null));
        self::assertNull(Upc::orNull('   '));
    }

    /** @return iterable<string, array{string}> */
    public static function invalid(): iterable
    {
        yield 'wrong check digit' => ['701518909441'];
        yield 'wrong EAN check digit' => ['4006381333932'];
        yield 'eleven digits' => ['70151890944'];
        yield 'valid GTIN-14' => ['00701518909440'];
        yield 'letter before a valid UPC' => ['A701518909440'];
        yield 'letter after a valid UPC' => ['7015189094408A'];
        yield 'empty after a sign' => ['+'];
        yield 'letters' => ['70151890944A'];
        yield 'inner space' => ['701518 909440'];
        yield 'dashes' => ['7-01518-90944-0'];
    }

    #[DataProvider('invalid')]
    public function testAnythingElseIsRefused(string $input): void
    {
        try {
            Upc::orNull($input);
            self::fail('An invalid UPC was accepted.');
        } catch (InvalidUpc $refused) {
            self::assertSame('music.invalid_upc', $refused->getMessage());
            self::assertSame(['upc' => trim($input)], $refused->parameters());
        }
    }
}
