<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

final readonly class SitemapPage
{
    /**
     * @param array<string, string> $params
     * @param array<int, string>    $images
     */
    public function __construct(
        public string $route,
        public array $params = [],
        public ?\DateTimeImmutable $lastmod = null,
        public array $images = [],
    ) {
    }
}
