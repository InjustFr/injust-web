<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Accounts;

use App\Application\Identity\SignIn\SignInHandler;
use App\Domain\Shared\Exception\DomainException;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AccountsAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly SignInAttempts $attempts,
        private readonly AccountsClient $accounts,
        private readonly OidcSingleSignOn $singleSignOn,
        private readonly SignInHandler $signIn,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'login_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $verifier = $this->attempts->finish($request->query->getString('state'));
        if ($request->query->has('error')) {
            throw new CustomUserMessageAuthenticationException('login.denied');
        }
        $code = $request->query->getString('code');
        if ('' === $code) {
            throw new CustomUserMessageAuthenticationException('login.failed');
        }

        $command = $this->accounts->signIn($code, $verifier, $this->singleSignOn->redirectUri());
        try {
            $user = ($this->signIn)($command);
        } catch (DomainException $exception) {
            throw new CustomUserMessageAuthenticationException($this->translator->trans($exception->getMessage(), $exception->parameters(), 'exceptions'), previous: $exception);
        }

        return new SelfValidatingPassport(new UserBadge((string) $user->id(), static fn () => SecurityUser::fromUser($user)), [new RememberMeBadge()]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $target = $this->getTargetPath($request->getSession(), $firewallName);
        $this->removeTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse(null === $target || str_contains($target, '/api/') ? $this->urls->generate('app') : $target);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urls->generate('login'));
    }
}
