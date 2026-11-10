<?php

declare(strict_types=1);

namespace App\Application\Production;

use App\Domain\Production\PortfolioItem;
use App\Domain\Production\ServiceKind;

final readonly class PortfolioItemView
{
    /** @param list<string> $roles */
    private function __construct(
        public string $id,
        public string $title,
        public string $artist,
        public array $roles,
        public ?int $year,
        public ?string $cover,
        public ?string $url,
        public int $position,
    ) {
    }

    public static function of(PortfolioItem $item): self
    {
        $details = $item->details();

        return new self($item->id()->toBase32(), $details->title, $details->artist, array_map(static fn (ServiceKind $role): string => $role->value, $details->roles), $details->year, $item->cover(), $details->url, $item->position());
    }
}
