<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Seo\SeoQueries;
use App\Application\Settings\SettingsQueries;
use App\Domain\Seo\PageKey;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class SiteLayout
{
    public function __construct(
        private SettingsQueries $queries,
        private LegalPages $legalPages,
        private SeoQueries $seo,
        private RequestStack $requests,
    ) {
    }

    /** @return array<string, mixed> */
    #[AsTwigFunction('site_layout')]
    public function context(): array
    {
        $settings = $this->queries->current();
        $key = PageKey::tryFrom($this->requests->getCurrentRequest()?->attributes->getString('_route') ?? '');

        return [
            'settings' => $settings,
            'legal_pages' => $this->legalPages->visible($settings),
            'page_seo' => null === $key ? null : $this->seo->page($key),
        ];
    }
}
