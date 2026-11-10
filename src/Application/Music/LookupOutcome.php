<?php

declare(strict_types=1);

namespace App\Application\Music;

enum LookupOutcome: string
{
    case Found = 'found';
    case NotFound = 'notFound';
    case Failed = 'failed';
    case NotConfigured = 'notConfigured';
}
