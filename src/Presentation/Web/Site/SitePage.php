<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Presentation\Web\Security\SecurityHeaders;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class SitePage
{
    public function __construct(
        private Environment $twig,
        private SiteLayout $layout,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = [], bool $hiddenFromVisitors = false): Response
    {
        $response = new Response();
        if ($hiddenFromVisitors) {
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->set('X-Robots-Tag', SecurityHeaders::NOINDEX);
        } else {
            $response->setPublic();
            $response->setMaxAge(0);
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        return $response->setContent($this->twig->render($template, $context + $this->layout->context() + [
            'hidden_from_visitors' => $hiddenFromVisitors,
        ]));
    }
}
