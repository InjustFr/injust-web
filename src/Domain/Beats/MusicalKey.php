<?php

declare(strict_types=1);

namespace App\Domain\Beats;

enum MusicalKey: string
{
    case CMajor = 'c-major';
    case CMinor = 'c-minor';
    case CSharpMajor = 'c-sharp-major';
    case CSharpMinor = 'c-sharp-minor';
    case DMajor = 'd-major';
    case DMinor = 'd-minor';
    case DSharpMajor = 'd-sharp-major';
    case DSharpMinor = 'd-sharp-minor';
    case EMajor = 'e-major';
    case EMinor = 'e-minor';
    case FMajor = 'f-major';
    case FMinor = 'f-minor';
    case FSharpMajor = 'f-sharp-major';
    case FSharpMinor = 'f-sharp-minor';
    case GMajor = 'g-major';
    case GMinor = 'g-minor';
    case GSharpMajor = 'g-sharp-major';
    case GSharpMinor = 'g-sharp-minor';
    case AMajor = 'a-major';
    case AMinor = 'a-minor';
    case ASharpMajor = 'a-sharp-major';
    case ASharpMinor = 'a-sharp-minor';
    case BMajor = 'b-major';
    case BMinor = 'b-minor';

    public function label(): string
    {
        return ucfirst(strtr($this->value, ['-sharp' => '#', '-' => ' ']));
    }
}
