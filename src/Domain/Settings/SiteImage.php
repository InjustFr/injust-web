<?php

declare(strict_types=1);

namespace App\Domain\Settings;

enum SiteImage: string
{
    case Logo = 'logo';
    case Hero = 'hero';
}
