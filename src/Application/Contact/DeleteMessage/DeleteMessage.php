<?php

declare(strict_types=1);

namespace App\Application\Contact\DeleteMessage;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteMessage
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
