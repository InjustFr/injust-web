<?php

declare(strict_types=1);

namespace App\Application\Links;

interface LinksQueries
{
    /** @return list<BioLinkView> */
    public function links(): array;

    /** @return list<BioLinkView> */
    public function visibleLinks(): array;
}
