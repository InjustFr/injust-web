<?php

declare(strict_types=1);

namespace App\Domain\Music;

use Symfony\Component\Uid\Ulid;

interface SocialLinkRepository
{
    public function add(SocialLink $item): void;

    public function get(Ulid $id): SocialLink;

    public function remove(SocialLink $item): void;

    /** @return list<SocialLink> */
    public function all(): array;
}
