<?php

declare(strict_types=1);

namespace App\Domain\Production;

enum Pricing: string
{
    case Fixed = 'fixed';
    case Quote = 'quote';
}
