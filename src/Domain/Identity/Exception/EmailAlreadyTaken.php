<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exception;

final class EmailAlreadyTaken extends InvalidUser
{
    public function __construct(string $email)
    {
        parent::__construct('user.email_taken', ['email' => $email]);
    }
}
