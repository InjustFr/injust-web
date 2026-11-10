<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Shared\LegalMarkdown;
use App\Presentation\Web\Site\FaqQuestions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FaqQuestionsTest extends TestCase
{
    public function testAFaqOfQuestionsBecomesQuestionsWithTheirAnswers(): void
    {
        $faq = new FaqQuestions(new LegalMarkdown());

        self::assertSame([
            ['question' => 'Do I need a guitar?', 'answer' => "<p>Yes, <em>any</em>.</p>\n"],
            ['question' => 'Where?', 'answer' => ''],
            ['question' => 'How <much>?', 'answer' => "<p>See &lt;b&gt;prices&lt;/b&gt;.</p>\n<ul>\n<li>one</li>\n</ul>\n"],
        ], $faq->questions("### Do I need a guitar?\nYes, *any*.\n###   Where?  \n### How <much>?\n\nSee <b>prices</b>.\n\n- one"));
    }

    public function testASingleQuestionIsEnough(): void
    {
        self::assertSame([['question' => 'Why?', 'answer' => "<p>Because.</p>\n"]], new FaqQuestions(new LegalMarkdown())->questions("### Why?\nBecause."));
    }

    /** @return iterable<string, array{string}> */
    public static function plainFaqs(): iterable
    {
        yield 'empty' => [''];
        yield 'text before the first question' => ["Intro.\n### Why?\nBecause."];
        yield 'no question' => ['Just text.'];
        yield 'a bigger heading inside' => ["### Why?\nBecause.\n## More\nText."];
        yield 'a title' => ["### Why?\n# Title"];
        yield 'a fourth level is not a question' => ['#### Why?'];
    }

    #[DataProvider('plainFaqs')]
    public function testAnyOtherFaqStaysPlain(string $markdown): void
    {
        self::assertNull(new FaqQuestions(new LegalMarkdown())->questions($markdown));
    }
}
