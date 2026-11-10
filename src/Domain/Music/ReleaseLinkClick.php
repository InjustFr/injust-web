<?php

declare(strict_types=1);

namespace App\Domain\Music;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'release_link_click')]
class ReleaseLinkClick
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Release::class, inversedBy: 'clicks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Release $release;

    #[ORM\Id]
    #[ORM\Column(length: 20, enumType: Platform::class)]
    private Platform $platform;

    #[ORM\Column]
    private int $clicks;

    public function platform(): Platform
    {
        return $this->platform;
    }

    public function clicks(): int
    {
        return $this->clicks;
    }
}
