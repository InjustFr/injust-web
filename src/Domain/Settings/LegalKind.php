<?php

declare(strict_types=1);

namespace App\Domain\Settings;

enum LegalKind: string
{
    case Mentions = 'mentions';
    case Privacy = 'privacy';
    case CgvLessons = 'cgv-lessons';
    case CgvProduction = 'cgv-production';
    case CgvBeats = 'cgv-beats';
    case Licence = 'licence';
}
