<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'], format: 'xml')]
final readonly class SitemapController
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public function __invoke(): Response
    {
        $home = htmlspecialchars($this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL), \ENT_XML1);

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>{$home}</loc></url>
            </urlset>

            XML;

        $response = new Response($xml, headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
