<?php

declare(strict_types=1);

namespace App\Application\Identity\SignIn;

use App\Application\Transaction;
use App\Domain\Identity\Exception\AccessDenied;
use App\Domain\Identity\Exception\EmailAlreadyTaken;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SignInHandler
{
    public function __construct(
        private UserRepository $users,
        private ClockInterface $clock,
        private Transaction $transaction,
        #[Autowire(env: 'OIDC_REQUIRED_GROUP')]
        private string $requiredGroup,
    ) {
    }

    public function __invoke(SignIn $command): User
    {
        if ('' !== $this->requiredGroup && !\in_array($this->requiredGroup, $command->groups, true)) {
            throw new AccessDenied();
        }

        $user = $this->users->findByAccountId($command->accountId) ?? $this->join($command);
        if (null !== $command->email) {
            $this->followEmail($user, $command->email);
        }
        $user->rename($command->displayName ?? $user->displayName());
        $this->transaction->commit();

        return $user;
    }

    private function join(SignIn $command): User
    {
        $email = User::normalizeEmail($command->email ?? throw new InvalidEmail(''));
        if (null !== $this->users->findByEmail($email)) {
            throw new EmailAlreadyTaken($email);
        }

        $user = User::join($command->accountId, $email, $command->displayName, $this->clock->now());
        $this->users->add($user);

        return $user;
    }

    private function followEmail(User $user, string $email): void
    {
        $email = User::normalizeEmail($email);
        if ($email === $user->email()) {
            return;
        }

        $holder = $this->users->findByEmail($email);
        if (null !== $holder && $holder !== $user) {
            throw new EmailAlreadyTaken($email);
        }
        $user->changeEmail($email);
    }
}
