<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Beats\BeatsQueries;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Beats\BeatDetails;
use App\Domain\Settings\Section;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/beats', 'fr' => '/fr/beats'], name: 'beats', methods: ['GET'])]
final readonly class BeatsController
{
    public function __construct(
        private SectionGate $gate,
        private BeatsQueries $beats,
        private BeatRows $rows,
        private TierColumns $tiers,
        private FaqQuestions $faq,
        private LegalMarkdown $markdown,
        private BeatsStructuredData $structuredData,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $hidden = $this->gate->hiddenFromVisitors(Section::Beats);
        $beats = $this->beats->page();
        $locale = $request->getLocale();
        $faq = $beats->faq->in($locale);
        $rows = $this->rows->rows($beats->beats);
        $filter = BeatFilter::from($request->query->all());

        return $this->page->render('site/beats.html.twig', [
            'section' => Section::Beats,
            'intro' => $this->markdown->toHtml($beats->intro->in($locale), []),
            'rows' => $rows,
            'shown' => array_values(array_filter($rows, $filter->keeps(...))),
            'filter' => $filter,
            'tags' => BeatRows::tags($rows),
            'keys' => BeatRows::keys($rows),
            'bounds' => ['min' => BeatDetails::MIN_BPM, 'max' => BeatDetails::MAX_BPM],
            'tiers' => $this->tiers->columns($beats->tiers, $locale),
            'faq_questions' => $this->faq->questions($faq),
            'faq' => $this->markdown->toHtml($faq, []),
            'page_graph' => $this->structuredData->recordings($beats->beats),
        ], $hidden);
    }
}
