<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
use App\Application\Settings\SettingsView;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\Section;

final readonly class LegalPages
{
    public function __construct(private SettingsQueries $queries)
    {
    }

    /** @return list<LegalKind> */
    public function visible(SettingsView $settings): array
    {
        $written = array_map(static fn (LegalDocumentView $document): LegalKind => $document->kind, $this->queries->legalDocuments());
        $visible = [];
        foreach (LegalKind::cases() as $kind) {
            if (\in_array($kind, $written, true) && self::shown($kind, $settings)) {
                $visible[] = $kind;
            }
        }

        return $visible;
    }

    private static function shown(LegalKind $kind, SettingsView $settings): bool
    {
        $section = match ($kind) {
            LegalKind::CgvLessons => Section::Lessons,
            LegalKind::CgvProduction => Section::Production,
            LegalKind::CgvBeats, LegalKind::Licence => Section::Beats,
            LegalKind::Mentions, LegalKind::Privacy => null,
        };

        return null === $section || $settings->isEnabled($section);
    }
}
