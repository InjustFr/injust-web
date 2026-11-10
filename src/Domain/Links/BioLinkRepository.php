<?php

declare(strict_types=1);

namespace App\Domain\Links;

use Symfony\Component\Uid\Ulid;

interface BioLinkRepository
{
    public function add(BioLink $link): void;

    public function get(Ulid $id): BioLink;

    public function remove(BioLink $link): void;

    /** @return list<BioLink> */
    public function all(): array;

    public function countClick(Ulid $id): ?string;
}
