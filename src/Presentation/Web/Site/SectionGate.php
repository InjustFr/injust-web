<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Settings\SettingsQueries;
use App\Domain\Settings\Section;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class SectionGate
{
    public function __construct(
        private SettingsQueries $settings,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function hiddenFromVisitors(Section $section): bool
    {
        if ($this->settings->current()->isEnabled($section)) {
            return false;
        }
        if (!$this->authorization->isGranted('ROLE_USER')) {
            throw new NotFoundHttpException();
        }

        return true;
    }
}
