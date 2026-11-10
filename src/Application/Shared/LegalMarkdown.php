<?php

declare(strict_types=1);

namespace App\Application\Shared;

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
        return $this->converter->convert(self::fill($markdown, $placeholders))->getContent();
    }

    /** @param array<string, string> $placeholders */
    public static function fill(string $markdown, array $placeholders): string
    {
        $values = [];
        foreach ($placeholders as $name => $value) {
            $values['{'.$name.'}'] = $value;
        }

        return strtr($markdown, $values);
    }
}
