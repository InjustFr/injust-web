<?php

declare(strict_types=1);

namespace App\Application\Links;

use App\Domain\Links\BioLinkRepository;

final readonly class StoredLinksQueries implements LinksQueries
{
    public function __construct(private BioLinkRepository $links)
    {
    }

    public function links(): array
    {
        return array_map(BioLinkView::of(...), $this->links->all());
    }

    public function visibleLinks(): array
    {
        return array_values(array_filter($this->links(), static fn (BioLinkView $link): bool => $link->visible));
    }
}
