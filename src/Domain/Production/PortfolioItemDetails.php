<?php

declare(strict_types=1);

namespace App\Domain\Production;

use App\Domain\Production\Exception\InvalidYear;
use App\Domain\Production\Exception\MissingRoles;
use App\Domain\Shared\HttpsUrl;

final readonly class PortfolioItemDetails
{
    public const int MIN_YEAR = 1990;
    public const int MAX_YEAR = 2100;

    public string $title;
    public string $artist;
    public ?string $url;

    /** @param list<ServiceKind> $roles */
    public function __construct(
        string $title,
        string $artist,
        public array $roles,
        public ?int $year,
        ?string $url,
    ) {
        if ([] === $roles) {
            throw new MissingRoles();
        }
        if (null !== $year && ($year < self::MIN_YEAR || $year > self::MAX_YEAR)) {
            throw new InvalidYear();
        }
        $this->title = trim($title);
        $this->artist = trim($artist);
        $url = HttpsUrl::orBlank($url ?? '');
        $this->url = '' === $url ? null : $url;
    }
}
