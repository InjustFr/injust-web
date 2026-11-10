<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Identity\User;
use App\Infrastructure\Security\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Ulid;

trait ActsAsUser
{
    protected static function createUser(?string $email = null): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $id = strtolower((string) new Ulid());
        $user = User::join($id, $email ?? \sprintf('%s@injust.test', $id), 'Louis', Clock::get()->now());
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    protected static function actAs(User $user): void
    {
        $securityUser = SecurityUser::fromUser($user);
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($securityUser, 'main', $securityUser->getRoles()));
    }
}
