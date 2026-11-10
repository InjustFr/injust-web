<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Exception\InvalidSlug;
use App\Domain\Music\Slug;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testATitleBecomesLowercaseAsciiKebab(): void
    {
        self::assertSame('no-gods-no-masters-ete-2026', Slug::of('  No Gods, No Masters — Été 2026! '));
    }

    public function testItIsCutAtTwoHundredCharactersWithoutATrailingDash(): void
    {
        self::assertSame(str_repeat('a', 199), Slug::of(str_repeat('a', 199).' b'));
        self::assertSame(str_repeat('a', 200), Slug::of(str_repeat('a', 250)));
    }

    public function testATextWithoutLettersOrDigitsIsRefused(): void
    {
        try {
            Slug::of(' !!! ');
            self::fail('The slug was accepted.');
        } catch (InvalidSlug $refused) {
            self::assertSame(['music.invalid_slug', ['slug' => ' !!! ']], [$refused->getMessage(), $refused->parameters()]);
        }
    }
}
