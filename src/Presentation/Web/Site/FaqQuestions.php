<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

final readonly class FaqQuestions
{
    public function __construct(private LegalMarkdown $markdown)
    {
    }

    /** @return list<array{question: string, answer: string}>|null */
    public function questions(string $markdown): ?array
    {
        $parts = preg_split('/^### +(.+)$/m', $markdown, flags: \PREG_SPLIT_DELIM_CAPTURE) ?: [];
        if (\count($parts) < 3 || '' !== $parts[0] || 1 === preg_match('/^#{1,2} /m', $markdown)) {
            return null;
        }
        $questions = [];
        for ($index = 1; $index < \count($parts); $index += 2) {
            $questions[] = ['question' => trim($parts[$index]), 'answer' => $this->markdown->toHtml($parts[$index + 1], [])];
        }

        return $questions;
    }
}
