<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Identity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Uid\Ulid;

/** @implements UserProviderInterface<SecurityUser> */
final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        if (!Ulid::isValid($identifier)) {
            throw new UserNotFoundException();
        }

        return $this->load(Ulid::fromString($identifier));
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user "%s".', $user::class));
        }

        return $this->load($user->id);
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    private function load(Ulid $id): SecurityUser
    {
        $user = $this->entityManager->find(User::class, $id) ?? throw new UserNotFoundException();

        return SecurityUser::fromUser($user);
    }
}
