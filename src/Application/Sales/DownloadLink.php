<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class DownloadLink
{
    public function __construct(
        public string $deliverable,
        public string $url,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
