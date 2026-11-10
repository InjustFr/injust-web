<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Settings\SettingsQueries;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
#[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'], format: 'xml')]
final readonly class SitemapController
{
    public function __construct(
        private SettingsQueries $queries,
        private LegalPages $legalPages,
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $settings = $this->queries->current();
        $pages = [['home', []]];
        foreach ($settings->enabledSections() as $section) {
            $pages[] = ['section', ['section' => $section->value]];
        }
        foreach ($this->legalPages->visible($settings) as $kind) {
            $pages[] = ['legal', ['kind' => $kind->value]];
        }

        $response = new Response($this->twig->render('site/sitemap.xml.twig', ['pages' => $pages]), headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
