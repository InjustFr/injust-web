<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Domain\Settings\Section;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[AsController]
#[Route(path: ['en' => '/{section}', 'fr' => '/fr/{section}'], name: 'section', requirements: ['section' => new EnumRequirement(Section::class)], methods: ['GET'])]
final readonly class SectionController
{
    public function __construct(
        private SectionGate $gate,
        private SitePage $page,
    ) {
    }

    public function __invoke(Section $section): Response
    {
        return $this->page->render('site/section.html.twig', ['section' => $section], $this->gate->hiddenFromVisitors($section));
    }
}
