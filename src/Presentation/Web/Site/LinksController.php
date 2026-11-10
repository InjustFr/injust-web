<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Links\LinksQueries;
use App\Application\Music\MusicQueries;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/links', 'fr' => '/fr/links'], name: 'links', methods: ['GET'])]
final readonly class LinksController
{
    public function __construct(
        private LinksQueries $links,
        private MusicQueries $music,
        private ClockInterface $clock,
        private SitePage $page,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->page->render('site/links.html.twig', [
            'music' => $this->music->home($this->clock->now()),
            'links' => $this->links->visibleLinks(),
        ]);
    }
}
