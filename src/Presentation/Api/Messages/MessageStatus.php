<?php

declare(strict_types=1);

namespace App\Presentation\Api\Messages;

enum MessageStatus: string
{
    case Open = 'open';
    case All = 'all';
}
