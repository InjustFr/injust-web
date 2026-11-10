<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Music\MusicQueries;
use App\Domain\Shared\Exception\NotFound;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/listen/{slug}', 'fr' => '/fr/listen/{slug}'], name: 'listen', requirements: ['slug' => ListenController::SLUG], methods: ['GET'])]
final readonly class ListenController
{
    public const string SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public function __construct(
        private MusicQueries $music,
        private ClockInterface $clock,
        private MusicStructuredData $structuredData,
        private SitePage $page,
    ) {
    }

    public function __invoke(string $slug): Response
    {
        try {
            $listen = $this->music->listen($slug, $this->clock->now());
        } catch (NotFound) {
            throw new NotFoundHttpException();
        }

        return $this->page->render('site/listen.html.twig', [
            'listen' => $listen,
            'release' => $listen->release,
            'links' => $listen->links,
            'page_title' => $listen->release->title,
            'og_image' => $listen->release->cover,
            'og_type' => 'single' === $listen->release->type ? 'music.song' : 'music.album',
            'page_graph' => $this->structuredData->listen($listen),
        ]);
    }
}
