<?php

declare(strict_types=1);

namespace App\Domain\Music;

enum ReleaseType: string
{
    case Single = 'single';
    case Ep = 'ep';
    case Album = 'album';
}
