<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Accounts;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final readonly class SignInAttempts
{
    private const string SESSION_KEY = '_accounts_sign_in';
    private const int KEPT = 5;

    public function __construct(private RequestStack $requestStack)
    {
    }

    /** @return array{state: string, challenge: string} */
    public function start(): array
    {
        $state = self::random(16);
        $verifier = self::random(32);
        $this->save([...\array_slice($this->pending(), -(self::KEPT - 1), preserve_keys: true), $state => $verifier]);

        return ['state' => $state, 'challenge' => self::base64Url(hash('sha256', $verifier, true))];
    }

    public function finish(string $state): string
    {
        $pending = $this->pending();
        $verifier = $pending[$state] ?? null;
        unset($pending[$state]);
        $this->save($pending);

        if ('' === $state || null === $verifier) {
            throw new CustomUserMessageAuthenticationException('login.expired');
        }

        return $verifier;
    }

    /** @return array<string, string> */
    private function pending(): array
    {
        $stored = $this->requestStack->getSession()->get(self::SESSION_KEY);
        $pending = [];
        foreach (\is_array($stored) ? $stored : [] as $state => $verifier) {
            if (\is_string($state) && \is_string($verifier)) {
                $pending[$state] = $verifier;
            }
        }

        return $pending;
    }

    /** @param array<string, string> $pending */
    private function save(array $pending): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $pending);
    }

    /** @param int<1, max> $bytes */
    private static function random(int $bytes): string
    {
        return self::base64Url(random_bytes($bytes));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
