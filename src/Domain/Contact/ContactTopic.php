<?php

declare(strict_types=1);

namespace App\Domain\Contact;

enum ContactTopic: string
{
    case General = 'general';
    case Lessons = 'lessons';
    case Production = 'production';
    case Beats = 'beats';
}
