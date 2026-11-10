<?php

declare(strict_types=1);

namespace App\Presentation\Web\Security;

use App\Presentation\Api\ProblemResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new ProblemResponse($this->translator->trans('problem.signed_out.title'), Response::HTTP_UNAUTHORIZED, $this->translator->trans('problem.signed_out.detail'));
        }

        return new RedirectResponse($this->urls->generate('login'));
    }
}
