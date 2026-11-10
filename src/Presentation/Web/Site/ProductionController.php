<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Production\ProductionQueries;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Settings\Section;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/production', 'fr' => '/fr/production'], name: 'production', methods: ['GET'])]
final readonly class ProductionController
{
    public function __construct(
        private SectionGate $gate,
        private ProductionQueries $production,
        private ProductionCards $cards,
        private FaqQuestions $faq,
        private LegalMarkdown $markdown,
        private ProductionStructuredData $structuredData,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $hidden = $this->gate->hiddenFromVisitors(Section::Production);
        $production = $this->production->page();
        $locale = $request->getLocale();
        $faq = $production->faq->in($locale);

        return $this->page->render('site/production.html.twig', [
            'section' => Section::Production,
            'intro' => $this->markdown->toHtml($production->intro->in($locale), []),
            'services' => $this->cards->cards($production->services, $locale),
            'process' => $this->markdown->toHtml($production->process->in($locale), []),
            'portfolio' => $production->portfolio,
            'faq_questions' => $this->faq->questions($faq),
            'faq' => $this->markdown->toHtml($faq, []),
            'page_graph' => [$this->structuredData->service($production->services, $locale)],
        ], $hidden);
    }
}
