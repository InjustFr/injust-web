<?php

declare(strict_types=1);

namespace App\Application\Settings\SwitchSections;

final readonly class SwitchSections
{
    public function __construct(
        public bool $lessons,
        public bool $production,
        public bool $beats,
    ) {
    }
}
