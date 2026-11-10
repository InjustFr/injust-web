<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Domain\Seo\PageKey;
use App\Domain\Seo\PageSeoRepository;

final readonly class StoredSeoQueries implements SeoQueries
{
    public function __construct(private PageSeoRepository $pages)
    {
    }

    public function page(PageKey $key): PageSeoView
    {
        return PageSeoView::of($this->pages->get($key));
    }

    public function all(): array
    {
        return array_map($this->page(...), PageKey::cases());
    }
}
