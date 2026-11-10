<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Settings\LegalDocumentHtml;
use App\Application\Settings\SettingsQueries;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[AsController]
#[Route(path: ['en' => '/legal/{kind}', 'fr' => '/fr/legal/{kind}'], name: 'legal', requirements: ['kind' => new EnumRequirement(LegalKind::class)], methods: ['GET'])]
final readonly class LegalController
{
    public function __construct(
        private SettingsQueries $queries,
        private LegalDocumentHtml $html,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request, LegalKind $kind): Response
    {
        try {
            $document = $this->queries->legalDocument($kind);
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException(previous: $notFound);
        }

        return $this->page->render('site/legal.html.twig', [
            'kind' => $kind,
            'body' => $this->html->render($document, $this->queries->current(), $request->getLocale(), $request->getSchemeAndHttpHost()),
        ]);
    }
}
