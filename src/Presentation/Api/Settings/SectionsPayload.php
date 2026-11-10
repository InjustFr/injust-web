<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

final readonly class SectionsPayload
{
    public function __construct(
        public bool $lessons = false,
        public bool $production = false,
        public bool $beats = false,
    ) {
    }
}
