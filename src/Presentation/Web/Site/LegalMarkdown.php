<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use League\CommonMark\CommonMarkConverter;

final readonly class LegalMarkdown
{
    private CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /** @param array<string, string> $placeholders */
    public function toHtml(string $markdown, array $placeholders): string
    {
        $values = [];
        foreach ($placeholders as $name => $value) {
            $values['{'.$name.'}'] = $value;
        }

        return $this->converter->convert(strtr($markdown, $values))->getContent();
    }
}
