<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Identity\CurrentUser;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class SecurityCurrentUser implements CurrentUser
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private UserRepository $users,
    ) {
    }

    public function get(): User
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('No signed-in user: personal data is only reachable for its owner.');
        }

        return $this->users->get($user->id);
    }
}
