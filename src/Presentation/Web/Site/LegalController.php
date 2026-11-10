<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Settings\LegalDocumentView;
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
        private LegalMarkdown $markdown,
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
            'body' => $this->markdown->toHtml($document->body->in($request->getLocale()), $this->placeholders($request, $document)),
        ]);
    }

    /** @return array<string, string> */
    private function placeholders(Request $request, LegalDocumentView $document): array
    {
        $settings = $this->queries->current();
        $legal = $settings->legal;
        $date = new \IntlDateFormatter($request->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, $document->updatedAt->getTimezone());

        return [
            'name' => $legal->name,
            'businessForm' => $legal->businessForm,
            'siren' => $legal->siren,
            'address' => $legal->address,
            'email' => $legal->email,
            'phone' => $legal->phone,
            'publicationDirector' => $legal->publicationDirector,
            'mediatorName' => $legal->mediatorName,
            'mediatorUrl' => $legal->mediatorUrl,
            'hostName' => $legal->hostName,
            'hostAddress' => $legal->hostAddress,
            'hostPhone' => $legal->hostPhone,
            'vatMention' => $settings->vatMentions->consumer,
            'siteUrl' => $request->getSchemeAndHttpHost(),
            'updatedAt' => (string) $date->format($document->updatedAt),
        ];
    }
}
