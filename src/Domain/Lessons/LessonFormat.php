<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

enum LessonFormat: string
{
    case AtHome = 'at-home';
    case AtTeacher = 'at-teacher';
    case Online = 'online';
}
