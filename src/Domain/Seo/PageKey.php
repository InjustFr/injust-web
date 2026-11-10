<?php

declare(strict_types=1);

namespace App\Domain\Seo;

enum PageKey: string
{
    case Lessons = 'lessons';
    case Production = 'production';
    case Contact = 'contact';
}
