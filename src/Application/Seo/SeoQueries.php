<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Domain\Seo\PageKey;

interface SeoQueries
{
    public function page(PageKey $key): PageSeoView;

    /** @return list<PageSeoView> */
    public function all(): array;
}
