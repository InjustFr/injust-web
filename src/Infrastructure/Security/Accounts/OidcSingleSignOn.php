<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Accounts;

use App\Application\Identity\SingleSignOn;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class OidcSingleSignOn implements SingleSignOn
{
    public function __construct(
        private AccountsConfiguration $configuration,
        private SignInAttempts $attempts,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function signInUrl(): string
    {
        $attempt = $this->attempts->start();

        return self::withQuery($this->configuration->authorizeUrl, [
            'response_type' => 'code',
            'client_id' => $this->configuration->clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'openid email profile',
            'state' => $attempt['state'],
            'code_challenge' => $attempt['challenge'],
            'code_challenge_method' => 'S256',
        ]);
    }

    public function signOutUrl(): string
    {
        return self::withQuery($this->configuration->logoutUrl, [
            'client_id' => $this->configuration->clientId,
            'post_logout_redirect_uri' => $this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    public function redirectUri(): string
    {
        return $this->urls->generate('login_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** @param array<string, string> $query */
    private static function withQuery(string $url, array $query): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }
}
