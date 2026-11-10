<?php

declare(strict_types=1);

namespace App\Application\Music;

final readonly class RefreshReport
{
    /** @param array<string, string> $report */
    public function __construct(
        public ReleaseView $release,
        public array $report,
    ) {
    }
}
