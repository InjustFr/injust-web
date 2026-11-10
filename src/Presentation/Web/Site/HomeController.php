<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Links\LinksQueries;
use App\Application\Music\MusicQueries;
use App\Application\Shared\LegalMarkdown;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/', 'fr' => '/fr'], name: 'home', methods: ['GET'])]
final readonly class HomeController
{
    public function __construct(
        private MusicQueries $music,
        private LinksQueries $links,
        private ClockInterface $clock,
        private LegalMarkdown $markdown,
        private MusicStructuredData $structuredData,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $home = $this->music->home($this->clock->now());
        $locale = $request->getLocale();

        return $this->page->render('site/home.html.twig', [
            'music' => $home,
            'links' => $this->links->visibleLinks(),
            'manifesto' => $this->markdown->toHtml($home->text->manifesto->in($locale), []),
            'bio' => $this->markdown->toHtml($home->text->bio->in($locale), []),
            'page_graph' => $this->structuredData->releases($home->releases),
        ]);
    }
}
