<?php

declare(strict_types=1);

namespace App\Presentation\Web\Security;

use App\Application\Identity\SingleSignOn;
use App\Presentation\Web\AuthPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/login', name: 'login', methods: ['GET'])]
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly SingleSignOn $singleSignOn,
        private readonly AuthPage $page,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(AuthenticationUtils $authentication): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app');
        }

        $error = $authentication->getLastAuthenticationError();
        if (null === $error) {
            return new RedirectResponse($this->singleSignOn->signInUrl());
        }

        return $this->page->render('LoginPage', ['error' => $this->message($error)]);
    }

    private function message(AuthenticationException $error): string
    {
        if ($error instanceof CustomUserMessageAuthenticationException) {
            return $this->translator->trans($error->getMessageKey(), $error->getMessageData());
        }

        return $this->translator->trans('login.failed');
    }
}
