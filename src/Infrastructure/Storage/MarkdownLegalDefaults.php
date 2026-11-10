<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Settings\LegalDefaults;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MarkdownLegalDefaults implements LegalDefaults
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/resources/legal')]
        private string $directory,
    ) {
    }

    public function body(LegalKind $kind): LocalizedText
    {
        return new LocalizedText($this->read($kind, 'fr'), $this->read($kind, 'en'));
    }

    private function read(LegalKind $kind, string $locale): string
    {
        $path = \sprintf('%s/%s.%s.md', $this->directory, $kind->value, $locale);

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
