<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Music\CountReleaseLinkClick\CountReleaseLinkClick;
use App\Application\Music\CountReleaseLinkClick\CountReleaseLinkClickHandler;
use App\Application\Music\MusicQueries;
use App\Domain\Music\Platform;
use App\Domain\Shared\Exception\NotFound;
use App\Presentation\Web\Security\SecurityHeaders;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[AsController]
#[Route('/listen/{slug}/{platform}', name: 'listen_click', requirements: ['slug' => ListenController::SLUG, 'platform' => new EnumRequirement(Platform::class)], methods: ['GET'])]
final readonly class ListenClickController
{
    public function __construct(
        private CountReleaseLinkClickHandler $countClick,
        private MusicQueries $music,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $slug, Platform $platform, Request $request): RedirectResponse
    {
        try {
            $url = $request->isMethod(Request::METHOD_HEAD) ? $this->visibleUrl($slug, $platform) : ($this->countClick)(new CountReleaseLinkClick($slug, $platform));
        } catch (NotFound) {
            throw new NotFoundHttpException();
        }

        $response = new RedirectResponse($url);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', SecurityHeaders::NOINDEX);

        return $response;
    }

    private function visibleUrl(string $slug, Platform $platform): string
    {
        foreach ($this->music->listen($slug, $this->clock->now())->links as $link) {
            if ($link->platform === $platform) {
                return $link->url;
            }
        }

        throw new NotFound('release_link', $platform->value);
    }
}
