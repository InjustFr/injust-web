<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Domain\Production\PortfolioItem;
use App\Domain\Production\PortfolioItemDetails;
use App\Domain\Production\ServiceKind;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PortfolioItemPayload
{
    /** @param list<ServiceKind> $roles */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: PortfolioItem::MAX_TITLE, normalizer: 'trim')]
        public string $title = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: PortfolioItem::MAX_ARTIST, normalizer: 'trim')]
        public string $artist = '',
        public array $roles = [],
        public ?int $year = null,
        #[Assert\Length(max: PortfolioItem::MAX_URL, normalizer: 'trim')]
        public ?string $url = null,
    ) {
    }

    public function details(): PortfolioItemDetails
    {
        return new PortfolioItemDetails($this->title, $this->artist, $this->roles, $this->year, $this->url);
    }
}
