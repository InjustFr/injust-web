<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\ConsentKind;
use App\Domain\Sales\ProductType;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\Section;
use PHPUnit\Framework\TestCase;

final class ProductTypeTest extends TestCase
{
    public function testServicesNeedTheTermsAnEarlyStartAndThePayProRataAcknowledgement(): void
    {
        self::assertSame([ConsentKind::Terms, ConsentKind::EarlyStart, ConsentKind::ProRata], ProductType::Lesson->requiredConsents());
        self::assertSame([ConsentKind::Terms, ConsentKind::EarlyStart, ConsentKind::ProRata], ProductType::Production->requiredConsents());
    }

    public function testABeatNeedsTheTermsTheDigitalWaiverAndTheLicence(): void
    {
        self::assertSame([ConsentKind::Terms, ConsentKind::DigitalWaiver, ConsentKind::Licence], ProductType::Beat->requiredConsents());
    }

    public function testEachTypeHasItsOwnTermsOfSaleAndSection(): void
    {
        self::assertSame([LegalKind::CgvLessons, LegalKind::CgvProduction, LegalKind::CgvBeats], array_map(static fn (ProductType $type): LegalKind => $type->cgvKind(), ProductType::cases()));
        self::assertSame([Section::Lessons, Section::Production, Section::Beats], array_map(static fn (ProductType $type): Section => $type->section(), ProductType::cases()));
    }
}
