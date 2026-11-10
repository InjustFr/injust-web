<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Settings;

use App\Domain\Settings\Exception\EmptyLegalDocument;
use App\Domain\Settings\LegalDocument;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\TestCase;

final class LegalDocumentTest extends TestCase
{
    public function testADocumentStartsAtVersionOne(): void
    {
        $now = new \DateTimeImmutable('2026-11-10 10:00');

        $document = LegalDocument::draft(LegalKind::CgvLessons, new LocalizedText('# CGV', '# Terms'), $now);

        self::assertSame([LegalKind::CgvLessons, 1, $now, '# CGV'], [$document->kind(), $document->version(), $document->updatedAt(), $document->body()->in('fr')]);
    }

    public function testEachEditIsANewVersion(): void
    {
        $document = LegalDocument::draft(LegalKind::Mentions, new LocalizedText('v1'), new \DateTimeImmutable('2026-11-10'));
        $later = new \DateTimeImmutable('2026-11-12');

        $document->edit(new LocalizedText('v2', 'v2'), $later);
        $document->edit(new LocalizedText('v3', 'v3'), $later);

        self::assertSame([3, $later, 'v3'], [$document->version(), $document->updatedAt(), $document->body()->in('en')]);
    }

    public function testAnEmptyBodyIsRefused(): void
    {
        $document = LegalDocument::draft(LegalKind::Privacy, new LocalizedText('ok'), new \DateTimeImmutable());

        $this->expectException(EmptyLegalDocument::class);

        $document->edit(new LocalizedText(' ', ''), new \DateTimeImmutable());
    }

    public function testADraftCannotBeEmptyEither(): void
    {
        $this->expectException(EmptyLegalDocument::class);

        LegalDocument::draft(LegalKind::Licence, new LocalizedText(), new \DateTimeImmutable());
    }
}
