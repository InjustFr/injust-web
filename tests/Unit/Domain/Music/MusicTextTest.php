<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Exception\InvalidHooks;
use App\Domain\Music\MusicText;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MusicTextTest extends TestCase
{
    public function testTheTextStartsEmpty(): void
    {
        $text = MusicText::initial();

        self::assertTrue($text->tagline()->isEmpty());
        self::assertTrue($text->manifesto()->isEmpty());
        self::assertTrue($text->bio()->isEmpty());
        self::assertSame([[], null], [$text->hooks(), $text->portrait()]);
    }

    public function testTheTextsAndHooksAreEditedTogetherAndBlankHooksDropped(): void
    {
        $text = MusicText::initial();

        $text->edit(new LocalizedText('Punk', 'Punk'), new LocalizedText('M fr', 'M en'), new LocalizedText('', 'Bio'), [' Burn it down ', '', '  ', 'Never kneel']);

        self::assertSame(['Punk', 'M en', 'Bio'], [$text->tagline()->in('fr'), $text->manifesto()->in('en'), $text->bio()->in('fr')]);
        self::assertSame(['Burn it down', 'Never kneel'], $text->hooks());
    }

    public function testTwelveHooksOfOneHundredTwentyCharactersFit(): void
    {
        $text = MusicText::initial();
        $hooks = array_fill(0, 12, str_repeat('é', 120));

        $text->edit(new LocalizedText(), new LocalizedText(), new LocalizedText(), $hooks);

        self::assertSame($hooks, $text->hooks());
    }

    /** @return iterable<array{list<string>}> */
    public static function tooMuch(): iterable
    {
        yield 'thirteen hooks' => [array_fill(0, 13, 'Hook')];
        yield 'a hook too long' => [['Short', str_repeat('a', 121)]];
    }

    /** @param list<string> $hooks */
    #[DataProvider('tooMuch')]
    public function testTooManyOrTooLongHooksAreRefused(array $hooks): void
    {
        $text = MusicText::initial();

        try {
            $text->edit(new LocalizedText(), new LocalizedText(), new LocalizedText(), $hooks);
            self::fail('The hooks were accepted.');
        } catch (InvalidHooks $refused) {
            self::assertSame(['music.invalid_hooks', ['max' => 12, 'length' => 120]], [$refused->getMessage(), $refused->parameters()]);
            self::assertSame([], $text->hooks());
        }
    }

    public function testThePortraitReturnsTheOneItReplaces(): void
    {
        $text = MusicText::initial();

        self::assertNull($text->usePortrait('P1'));
        self::assertSame('P1', $text->usePortrait('P2'));
        self::assertSame('P2', $text->removePortrait());
        self::assertNull($text->portrait());
    }
}
