<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Music\MusicQueries;
use App\Application\Music\ReleaseView;
use App\Application\Settings\SettingsQueries;
use App\Domain\Settings\Section;
use Psr\Clock\ClockInterface;
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
        private MusicQueries $music,
        private ClockInterface $clock,
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $settings = $this->queries->current();
        $covers = array_filter(array_map(static fn (ReleaseView $release): ?string => $release->cover, $this->music->releases($this->clock->now())));
        $pages = [new SitemapPage('home', images: $covers)];
        foreach ($settings->enabledSections() as $section) {
            $pages[] = Section::Beats === $section ? new SitemapPage('section', ['section' => $section->value]) : new SitemapPage($section->value);
        }
        $pages[] = new SitemapPage('contact');
        $pages[] = new SitemapPage('links');
        foreach ($this->legalPages->visible($settings) as $kind) {
            $pages[] = new SitemapPage('legal', ['kind' => $kind->value], $this->queries->legalDocument($kind)->updatedAt);
        }

        $response = new Response($this->twig->render('site/sitemap.xml.twig', ['pages' => $pages]), headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
