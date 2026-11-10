<?php

declare(strict_types=1);

namespace App\Domain\Sales;

enum ConsentKind: string
{
    case Terms = 'terms';
    case EarlyStart = 'early-start';
    case ProRata = 'pro-rata';
    case DigitalWaiver = 'digital-waiver';
    case Licence = 'licence';
}
