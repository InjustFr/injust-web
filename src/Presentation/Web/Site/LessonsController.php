<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Lessons\LessonsQueries;
use App\Domain\Settings\Section;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/lessons', 'fr' => '/fr/lessons'], name: 'lessons', methods: ['GET'])]
final readonly class LessonsController
{
    public function __construct(
        private SectionGate $gate,
        private LessonsQueries $lessons,
        private LessonCards $cards,
        private FaqQuestions $faq,
        private LegalMarkdown $markdown,
        private LessonsStructuredData $structuredData,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $hidden = $this->gate->hiddenFromVisitors(Section::Lessons);
        $lessons = $this->lessons->page();
        $locale = $request->getLocale();
        $faq = $lessons->faq->in($locale);

        return $this->page->render('site/lessons.html.twig', [
            'section' => Section::Lessons,
            'intro' => $this->markdown->toHtml($lessons->intro->in($locale), []),
            'groups' => $this->cards->groups($lessons->offers, $locale),
            'faq_questions' => $this->faq->questions($faq),
            'faq' => $this->markdown->toHtml($faq, []),
            'page_graph' => [$this->structuredData->service($lessons->offers, $locale)],
        ], $hidden);
    }
}
