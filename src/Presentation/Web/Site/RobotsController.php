<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Settings\SettingsQueries;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route('/robots.txt', name: 'robots', methods: ['GET'], format: 'txt')]
final readonly class RobotsController
{
    private const array PRIVATE_PATHS = ['/admin', '/api/', '/go/', '/checkout', '/fr/checkout', '/login', '/logout'];

    public function __construct(
        private SettingsQueries $settings,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(): Response
    {
        $lines = ['User-agent: *', 'Disallow: /'];
        if ($this->settings->current()->searchIndexing) {
            $lines = ['User-agent: *', 'Allow: /'];
            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.$this->urls->generate('sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $response = new Response(implode("\n", $lines)."\n");
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
