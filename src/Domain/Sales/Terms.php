<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class Terms
{
    public function __construct(
        #[ORM\Column]
        public private(set) int $version,
        #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'body_')]
        public private(set) LocalizedText $body,
        #[ORM\Column(type: 'datetime_immutable')]
        public private(set) \DateTimeImmutable $publishedAt,
    ) {
    }
}
