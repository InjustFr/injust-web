<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Accounts;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AccountsConfiguration
{
    public function __construct(
        #[Autowire(env: 'OIDC_CLIENT_ID')]
        public string $clientId,
        #[Autowire(env: 'OIDC_CLIENT_SECRET')]
        public string $clientSecret,
        #[Autowire(env: 'OIDC_AUTHORIZE_URL')]
        public string $authorizeUrl,
        #[Autowire(env: 'OIDC_TOKEN_URL')]
        public string $tokenUrl,
        #[Autowire(env: 'OIDC_USERINFO_URL')]
        public string $userinfoUrl,
        #[Autowire(env: 'OIDC_LOGOUT_URL')]
        public string $logoutUrl,
    ) {
    }
}
