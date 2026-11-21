<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Music\MusicQueries;
use App\Presentation\Web\Security\SecurityHeaders;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route('/latest-music', name: 'latest_music', methods: ['GET'])]
final readonly class LatestMusicController
{
    public function __construct(
        private MusicQueries $music,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(): RedirectResponse
    {
        $response = new RedirectResponse($this->target());
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', SecurityHeaders::NOINDEX);

        return $response;
    }

    private function target(): string
    {
        $release = $this->music->home($this->clock->now())->featured;
        if (null === $release) {
            return $this->urls->generate('home');
        }

        $listenPage = [] !== $release->links || null !== $release->upc;
        if (null !== $release->smartLinkUrl && ($release->upcoming || !$listenPage)) {
            return $release->smartLinkUrl;
        }

        return $this->urls->generate('listen', ['slug' => $release->slug, '_locale' => 'en']);
    }
}
