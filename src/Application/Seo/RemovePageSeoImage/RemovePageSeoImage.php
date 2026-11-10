<?php

declare(strict_types=1);

namespace App\Application\Seo\RemovePageSeoImage;

use App\Domain\Seo\PageKey;

final readonly class RemovePageSeoImage
{
    public function __construct(public PageKey $key)
    {
    }
}
