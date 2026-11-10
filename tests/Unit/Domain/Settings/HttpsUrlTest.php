<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Settings;

use App\Domain\Settings\Exception\InvalidUrl;
use App\Domain\Settings\HttpsUrl;
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

    #[DataProvider('refused')]
    public function testAnythingButAnHttpsUrlIsRefused(string $url): void
    {
        try {
            HttpsUrl::orBlank($url);
            self::fail('The URL was accepted.');
        } catch (InvalidUrl $refused) {
            self::assertSame(['settings.invalid_url', ['url' => $url]], [$refused->getMessage(), $refused->parameters()]);
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
