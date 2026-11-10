<?php

declare(strict_types=1);

namespace App\Presentation\Web\Security;

use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/logout', name: 'logout', methods: ['POST'])]
final class LogoutController
{
    public function __invoke(): never
    {
        throw new \LogicException('Handled by the firewall logout listener.');
    }
}
