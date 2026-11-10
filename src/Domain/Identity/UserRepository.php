<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use Symfony\Component\Uid\Ulid;

interface UserRepository
{
    public function add(User $user): void;

    public function get(Ulid $id): User;

    public function findByEmail(string $email): ?User;

    public function findByAccountId(string $accountId): ?User;
}
