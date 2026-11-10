<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

final readonly class OrderPayload
{
    /** @param list<string> $ids */
    public function __construct(public array $ids = [])
    {
    }
}
