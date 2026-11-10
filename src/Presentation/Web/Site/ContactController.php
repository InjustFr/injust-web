<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Contact\ContactOffers;
use App\Application\Settings\SettingsQueries;
use App\Domain\Contact\ContactTopic;
use App\Domain\Settings\Section;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/contact', 'fr' => '/fr/contact'], name: 'contact', methods: ['GET'])]
final readonly class ContactController
{
    public function __construct(
        private SettingsQueries $settings,
        private ContactOffers $offers,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $topics = [ContactTopic::General->value, ...array_map(static fn (Section $section): string => $section->value, $this->settings->current()->enabledSections())];
        $topic = \in_array($request->query->getString('topic'), $topics, true) ? $request->query->getString('topic') : ContactTopic::General->value;

        return $this->page->render('site/contact.html.twig', [
            'topics' => $topics,
            'topic' => $topic,
            'offer' => $this->offers->find(ContactTopic::from($topic), $request->query->getString('offer'), $request->getLocale()),
        ]);
    }
}
