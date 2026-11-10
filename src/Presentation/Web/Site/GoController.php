<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Links\CountBioLinkClick\CountBioLinkClick;
use App\Application\Links\CountBioLinkClick\CountBioLinkClickHandler;
use App\Application\Links\LinksQueries;
use App\Domain\Shared\Exception\NotFound;
use App\Presentation\Web\Security\SecurityHeaders;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[AsController]
#[Route('/go/{id}', name: 'go', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
final readonly class GoController
{
    public function __construct(
        private CountBioLinkClickHandler $countClick,
        private LinksQueries $links,
    ) {
    }

    public function __invoke(Ulid $id, Request $request): RedirectResponse
    {
        try {
            $url = $request->isMethod(Request::METHOD_HEAD) ? $this->visibleUrl($id) : ($this->countClick)(new CountBioLinkClick($id));
        } catch (NotFound) {
            throw new NotFoundHttpException();
        }

        $response = new RedirectResponse($url);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', SecurityHeaders::NOINDEX);

        return $response;
    }

    private function visibleUrl(Ulid $id): string
    {
        foreach ($this->links->visibleLinks() as $link) {
            if ($link->id === $id->toBase32()) {
                return $link->url;
            }
        }

        throw new NotFound('bio_link', $id->toBase32());
    }
}
