<?php

declare(strict_types=1);

namespace App\Application\Music;

final readonly class FinderView
{
    public function __construct(
        public string $platform,
        public bool $configured,
    ) {
    }

    public static function of(PlatformFinder $finder): self
    {
        return new self($finder->platform()->value, $finder->configured());
    }
}
