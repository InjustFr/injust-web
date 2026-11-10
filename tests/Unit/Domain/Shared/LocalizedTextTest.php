<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\TestCase;

final class LocalizedTextTest extends TestCase
{
    public function testEachLocaleReadsItsOwnSide(): void
    {
        $text = new LocalizedText('Bonjour', 'Hello');

        self::assertSame(['Bonjour', 'Hello'], [$text->in('fr'), $text->in('en')]);
    }

    public function testAnEmptySideFallsBackToTheOther(): void
    {
        self::assertSame('Hello', new LocalizedText('', 'Hello')->in('fr'));
        self::assertSame('Bonjour', new LocalizedText('Bonjour', '  ')->in('en'));
    }

    public function testAnyOtherLocaleReadsTheEnglishSide(): void
    {
        self::assertSame('Hello', new LocalizedText('Bonjour', 'Hello')->in('de'));
    }

    public function testSidesAreTrimmed(): void
    {
        $text = new LocalizedText("  Bonjour\n", "\tHello ");

        self::assertSame(['Bonjour', 'Hello'], [$text->fr, $text->en]);
    }

    public function testItIsEmptyOnlyWhenBothSidesAreBlank(): void
    {
        self::assertTrue(new LocalizedText()->isEmpty());
        self::assertTrue(new LocalizedText(' ', "\n")->isEmpty());
        self::assertFalse(new LocalizedText('', 'Hello')->isEmpty());
        self::assertFalse(new LocalizedText('Bonjour')->isEmpty());
    }

    public function testItIsSerializedAsBothSides(): void
    {
        self::assertSame('{"fr":"Bonjour","en":"Hello"}', json_encode(new LocalizedText('Bonjour', 'Hello')));
    }
}
