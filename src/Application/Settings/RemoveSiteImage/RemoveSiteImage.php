<?php

declare(strict_types=1);

namespace App\Application\Settings\RemoveSiteImage;

use App\Domain\Settings\SiteImage;

final readonly class RemoveSiteImage
{
    public function __construct(public SiteImage $slot)
    {
    }
}
