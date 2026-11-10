<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Seo;

use App\Domain\Seo\Exception\InvalidPageSeo;
use App\Domain\Seo\PageKey;
use App\Domain\Seo\PageSeo;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageSeoTest extends TestCase
{
    public function testABlankPageHasNoTitleDescriptionOrImage(): void
    {
        $seo = PageSeo::blank(PageKey::Links);

        self::assertSame([PageKey::Links, true, true, null], [$seo->key(), $seo->title()->isEmpty(), $seo->description()->isEmpty(), $seo->image()]);
    }

    public function testTheTitleAndDescriptionAreEdited(): void
    {
        $seo = PageSeo::blank(PageKey::Lessons);

        $seo->edit(new LocalizedText(str_repeat('é', 70), str_repeat('è', 70)), new LocalizedText(str_repeat('ê', 170), str_repeat('ë', 170)));

        self::assertSame([70, 70, 170, 170], [mb_strlen($seo->title()->fr), mb_strlen($seo->title()->en), mb_strlen($seo->description()->fr), mb_strlen($seo->description()->en)]);
    }

    /** @return iterable<string, array{LocalizedText, LocalizedText}> */
    public static function tooLong(): iterable
    {
        yield 'french title' => [new LocalizedText(str_repeat('a', 71)), new LocalizedText()];
        yield 'english title' => [new LocalizedText('', str_repeat('a', 71)), new LocalizedText()];
        yield 'french description' => [new LocalizedText(), new LocalizedText(str_repeat('a', 171))];
        yield 'english description' => [new LocalizedText(), new LocalizedText('', str_repeat('a', 171))];
    }

    #[DataProvider('tooLong')]
    public function testATextOverItsLimitIsRefused(LocalizedText $title, LocalizedText $description): void
    {
        $seo = PageSeo::blank(PageKey::Contact);

        try {
            $seo->edit($title, $description);
            self::fail('A text over its limit was accepted.');
        } catch (InvalidPageSeo $refused) {
            self::assertSame(['title' => 70, 'description' => 170], $refused->parameters());
        }
        self::assertTrue($seo->title()->isEmpty());
    }

    public function testAnImageIsUsedReplacedAndRemoved(): void
    {
        $seo = PageSeo::blank(PageKey::Production);

        self::assertNull($seo->useImage('FIRST'));
        self::assertSame('FIRST', $seo->useImage('SECOND'));
        self::assertSame('SECOND', $seo->image());
        self::assertSame('SECOND', $seo->removeImage());
        self::assertNull($seo->image());
    }
}
