<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exception;

final class InvalidEmail extends InvalidUser
{
    public function __construct(string $email)
    {
        parent::__construct('user.invalid_email', ['email' => $email]);
    }
}
