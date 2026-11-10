<?php

declare(strict_types=1);

namespace App\Domain\Seo;

interface PageSeoRepository
{
    public function get(PageKey $key): PageSeo;

    public function save(PageSeo $page): void;
}
