<?php

declare(strict_types=1);

namespace App\Domain\Music;

enum LinkSource: string
{
    case Manual = 'manual';
    case Auto = 'auto';
}
