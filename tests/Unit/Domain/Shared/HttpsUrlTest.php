<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\Exception\InvalidUrl;
use App\Domain\Shared\HttpsUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpsUrlTest extends TestCase
{
    public function testAnHttpsUrlIsKeptTrimmed(): void
    {
        self::assertSame('https://cal.test/injust', HttpsUrl::orBlank(' https://cal.test/injust '));
    }

    public function testABlankUrlStaysBlank(): void
    {
        self::assertSame('', HttpsUrl::orBlank('  '));
    }

    public function testARequiredUrlIsKeptTrimmed(): void
    {
        self::assertSame('https://ffm.to/injust', HttpsUrl::of(' https://ffm.to/injust '));
    }

    public function testARequiredUrlCannotBeBlank(): void
    {
        try {
            HttpsUrl::of('  ');
            self::fail('The blank URL was accepted.');
        } catch (InvalidUrl $refused) {
            self::assertSame(['shared.invalid_url', ['url' => '']], [$refused->getMessage(), $refused->parameters()]);
        }
    }

    #[DataProvider('refused')]
    public function testARequiredUrlRefusesAnythingButHttps(string $url): void
    {
        $this->expectException(InvalidUrl::class);

        HttpsUrl::of($url);
    }

    #[DataProvider('refused')]
    public function testAnythingButAnHttpsUrlIsRefused(string $url): void
    {
        try {
            HttpsUrl::orBlank($url);
            self::fail('The URL was accepted.');
        } catch (InvalidUrl $refused) {
            self::assertSame(['shared.invalid_url', ['url' => $url]], [$refused->getMessage(), $refused->parameters()]);
        }
    }

    /** @return iterable<array{string}> */
    public static function refused(): iterable
    {
        yield ['http://cal.test'];
        yield ['cal.test'];
        yield ['javascript:alert(1)'];
    }
}
