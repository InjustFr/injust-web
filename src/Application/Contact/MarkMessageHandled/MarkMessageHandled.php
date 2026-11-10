<?php

declare(strict_types=1);

namespace App\Application\Contact\MarkMessageHandled;

use Symfony\Component\Uid\Ulid;

final readonly class MarkMessageHandled
{
    public function __construct(
        public Ulid $id,
        public bool $handled,
    ) {
    }
}
