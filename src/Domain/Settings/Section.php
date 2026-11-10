<?php

declare(strict_types=1);

namespace App\Domain\Settings;

enum Section: string
{
    case Lessons = 'lessons';
    case Production = 'production';
    case Beats = 'beats';
}
