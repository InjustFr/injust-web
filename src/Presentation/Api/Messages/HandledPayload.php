<?php

declare(strict_types=1);

namespace App\Presentation\Api\Messages;

final readonly class HandledPayload
{
    public function __construct(public bool $handled)
    {
    }
}
