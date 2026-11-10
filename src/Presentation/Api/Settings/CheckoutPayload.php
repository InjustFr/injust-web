<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

final readonly class CheckoutPayload
{
    public function __construct(public bool $enabled = false)
    {
    }
}
