<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\User;

interface CurrentUser
{
    public function get(): User;
}
