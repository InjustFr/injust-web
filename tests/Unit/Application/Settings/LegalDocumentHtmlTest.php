<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Settings;

use App\Application\Settings\LegalDocumentHtml;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Settings\LegalKind;
use App\Tests\Support\Sales\DocumentExamples;
use PHPUnit\Framework\TestCase;

final class LegalDocumentHtmlTest extends TestCase
{
    public function testTheDocumentIsRenderedInTheLocaleWithEveryPlaceholderFilled(): void
    {
        $placeholders = ['name', 'businessForm', 'siren', 'address', 'email', 'phone', 'publicationDirector', 'mediatorName', 'mediatorUrl', 'hostName', 'hostAddress', 'hostPhone', 'vatMention', 'siteUrl', 'updatedAt'];
        $markdown = implode(' | ', array_map(static fn (string $name): string => '{'.$name.'}', $placeholders));
        $document = DocumentExamples::document(LegalKind::Mentions, 'FR '.$markdown, 'EN '.$markdown);

        $html = new LegalDocumentHtml(new LegalMarkdown())->render($document, DocumentExamples::settings(), 'fr', 'https://injust.test');

        self::assertSame("<p>FR Louis Injust | EI | 443061841 | 1 rue du Son\n75000 Paris | louis@injust.test | +33600000000 | Louis Injust | Médiateur Test | https://mediator.test | OVH SAS | Roubaix | +33972101007 | TVA non applicable – art. 293 B du CGI | https://injust.test | 1 novembre 2026</p>\n", $html);
    }

    public function testTheUpdateDateIsWrittenInTheRequestedLocale(): void
    {
        $html = new LegalDocumentHtml(new LegalMarkdown())->render(DocumentExamples::document(LegalKind::Mentions, 'FR {updatedAt}', 'EN {updatedAt}'), DocumentExamples::settings(), 'en', 'https://injust.test');

        self::assertSame("<p>EN November 1, 2026</p>\n", $html);
    }
}
