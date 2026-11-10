<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Shared;

use App\Application\Shared\LegalMarkdown;
use PHPUnit\Framework\TestCase;

final class LegalMarkdownTest extends TestCase
{
    public function testPlaceholdersAreReplacedAndUnknownOnesAreLeftAsTheyAre(): void
    {
        self::assertSame("<p>Louis, SIREN {siren}, {unknown}.</p>\n", new LegalMarkdown()->toHtml('{name}, SIREN {siren}, {unknown}.', ['name' => 'Louis']));
    }

    public function testHtmlInTheTextOrInAPlaceholderIsEscaped(): void
    {
        $html = new LegalMarkdown()->toHtml("<script>alert(1)</script>\n\n{name}", ['name' => '<img src=x onerror=alert(1)>']);

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function testUnsafeLinksLoseTheirTarget(): void
    {
        $html = new LegalMarkdown()->toHtml('[x](javascript:alert(1)) [y](https://ok.test)', []);

        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('href="https://ok.test"', $html);
    }
}
