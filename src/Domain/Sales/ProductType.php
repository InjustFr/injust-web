<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Settings\LegalKind;
use App\Domain\Settings\Section;

enum ProductType: string
{
    case Lesson = 'lesson';
    case Production = 'production';
    case Beat = 'beat';

    /** @return list<ConsentKind> */
    public function requiredConsents(): array
    {
        return self::Beat === $this
            ? [ConsentKind::Terms, ConsentKind::DigitalWaiver, ConsentKind::Licence]
            : [ConsentKind::Terms, ConsentKind::EarlyStart, ConsentKind::ProRata];
    }

    public function cgvKind(): LegalKind
    {
        return match ($this) {
            self::Lesson => LegalKind::CgvLessons,
            self::Production => LegalKind::CgvProduction,
            self::Beat => LegalKind::CgvBeats,
        };
    }

    public function section(): Section
    {
        return match ($this) {
            self::Lesson => Section::Lessons,
            self::Production => Section::Production,
            self::Beat => Section::Beats,
        };
    }
}
