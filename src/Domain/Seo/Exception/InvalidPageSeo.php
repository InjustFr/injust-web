<?php

declare(strict_types=1);

namespace App\Domain\Seo\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidPageSeo extends DomainException
{
    public function __construct(int $title, int $description)
    {
        parent::__construct('seo.invalid_page_seo', ['title' => $title, 'description' => $description]);
    }
}
