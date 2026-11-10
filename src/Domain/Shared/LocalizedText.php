<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class LocalizedText implements \JsonSerializable
{
    #[ORM\Column(type: 'text')]
    public string $fr;

    #[ORM\Column(type: 'text')]
    public string $en;

    public function __construct(string $fr = '', string $en = '')
    {
        $this->fr = trim($fr);
        $this->en = trim($en);
    }

    public function in(string $locale): string
    {
        [$own, $other] = 'fr' === $locale ? [$this->fr, $this->en] : [$this->en, $this->fr];

        return '' === $own ? $other : $own;
    }

    public function isEmpty(): bool
    {
        return '' === $this->fr && '' === $this->en;
    }

    /** @return array{fr: string, en: string} */
    public function jsonSerialize(): array
    {
        return ['fr' => $this->fr, 'en' => $this->en];
    }
}
