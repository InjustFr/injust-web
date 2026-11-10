<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Identity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

final class SecurityUser implements UserInterface
{
    private function __construct(
        public readonly Ulid $id,
        public readonly string $email,
        public readonly string $displayName,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->id(), $user->email(), $user->displayName());
    }

    public function getUserIdentifier(): string
    {
        return $this->id->toBase32();
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function __serialize(): array
    {
        return [
            'id' => (string) $this->id,
            'email' => $this->email,
            'displayName' => $this->displayName,
        ];
    }

    /** @param array{id: string, email: string, displayName: string} $data */
    public function __unserialize(array $data): void
    {
        $this->id = Ulid::fromString($data['id']);
        $this->email = $data['email'];
        $this->displayName = $data['displayName'];
    }
}
