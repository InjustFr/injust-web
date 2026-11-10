<?php

declare(strict_types=1);

namespace App\Application\Identity;

interface SingleSignOn
{
    public function signInUrl(): string;

    public function signOutUrl(): string;
}
