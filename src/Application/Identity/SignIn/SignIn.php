<?php

declare(strict_types=1);

namespace App\Application\Identity\SignIn;

final readonly class SignIn
{
    /** @param list<string> $groups */
    public function __construct(
        public string $accountId,
        public ?string $email,
        public ?string $displayName = null,
        public array $groups = [],
    ) {
    }
}
