<?php

declare(strict_types=1);

namespace App\Presentation\Web;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class AuthPage
{
    public function __construct(private Environment $twig)
    {
    }

    /**
     * @param array<string, mixed> $props
     */
    public function render(string $component, array $props, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render('auth.html.twig', ['component' => $component, 'props' => $props]), $status);
    }
}
