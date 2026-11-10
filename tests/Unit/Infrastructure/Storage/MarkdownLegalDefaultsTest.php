<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Domain\Settings\LegalKind;
use App\Infrastructure\Storage\MarkdownLegalDefaults;
use PHPUnit\Framework\TestCase;

final class MarkdownLegalDefaultsTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../Fixtures/legal';

    public function testEachLanguageIsReadFromItsMarkdownFile(): void
    {
        $body = new MarkdownLegalDefaults(self::FIXTURES)->body(LegalKind::Mentions);

        self::assertSame(trim((string) file_get_contents(self::FIXTURES.'/mentions.fr.md')), $body->fr);
        self::assertSame(trim((string) file_get_contents(self::FIXTURES.'/mentions.en.md')), $body->en);
        self::assertStringStartsWith('# Mentions', $body->fr);
        self::assertStringStartsWith('# Legal notice', $body->en);
    }

    public function testAMissingFileGivesAnEmptySide(): void
    {
        $defaults = new MarkdownLegalDefaults(self::FIXTURES);

        self::assertSame(['# Politique de confidentialité', ''], [$defaults->body(LegalKind::Privacy)->fr, $defaults->body(LegalKind::Privacy)->en]);
        self::assertTrue($defaults->body(LegalKind::Licence)->isEmpty());
    }
}
