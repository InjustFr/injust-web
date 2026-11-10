<?php

declare(strict_types=1);

namespace App\Application\Settings\SwitchOnlineCheckout;

final readonly class SwitchOnlineCheckout
{
    public function __construct(public bool $enabled)
    {
    }
}
