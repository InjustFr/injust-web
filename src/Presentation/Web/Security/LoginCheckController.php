<?php

declare(strict_types=1);

namespace App\Presentation\Web\Security;

use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/login/check', name: 'login_check', methods: ['GET'])]
final class LoginCheckController
{
    public function __invoke(): never
    {
        throw new \LogicException('Handled by the accounts authenticator.');
    }
}
