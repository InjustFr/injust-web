<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Links;

use App\Domain\Links\BioLink;
use App\Domain\Links\Exception\InvalidLinkLabel;
use App\Domain\Music\SocialNetwork;
use App\Domain\Shared\Exception\InvalidUrl;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\TestCase;

final class BioLinkTest extends TestCase
{
    public function testANewLinkKeepsItsFlagsAndIsNeverClicked(): void
    {
        $link = BioLink::add(new LocalizedText(' Écouter ', ' Listen '), ' https://open.spotify.com/artist/injust ', SocialNetwork::Spotify, true, false, 2);

        self::assertSame(['Écouter', 'Listen'], [$link->label()->fr, $link->label()->en]);
        self::assertSame(['https://open.spotify.com/artist/injust', SocialNetwork::Spotify, 2], [$link->url(), $link->icon(), $link->position()]);
        self::assertSame([true, false, 0], [$link->highlighted(), $link->visible(), $link->clicks()]);
    }

    public function testALinkIsEditedHighlightedHiddenAndMoved(): void
    {
        $link = BioLink::add(new LocalizedText('A', 'A'), 'https://injust.test', null, false, true, 0);

        $link->edit(new LocalizedText('', 'Merch'), 'https://shop.injust.test', null, true, false);
        $link->moveTo(4);

        self::assertSame(['', 'Merch', 'https://shop.injust.test', null], [$link->label()->fr, $link->label()->en, $link->url(), $link->icon()]);
        self::assertSame([true, false, 4], [$link->highlighted(), $link->visible(), $link->position()]);
        $link->edit(new LocalizedText('', 'Merch'), 'https://shop.injust.test', null, false, true);
        self::assertSame([false, true], [$link->highlighted(), $link->visible()]);
    }

    public function testTheUrlMustBeHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        BioLink::add(new LocalizedText('A', 'A'), 'http://injust.test', null, false, true, 0);
    }

    public function testALabelIsNeeded(): void
    {
        $this->expectException(InvalidLinkLabel::class);

        BioLink::add(new LocalizedText(' ', ''), 'https://injust.test', null, false, true, 0);
    }

    public function testEachSideOfTheLabelKeepsToEightyCharacters(): void
    {
        $link = BioLink::add(new LocalizedText(str_repeat('é', 80), str_repeat('è', 80)), 'https://injust.test', null, false, true, 0);
        self::assertSame([80, 80], [mb_strlen($link->label()->fr), mb_strlen($link->label()->en)]);

        foreach ([new LocalizedText(str_repeat('é', 81), 'ok'), new LocalizedText('ok', str_repeat('e', 81))] as $label) {
            try {
                $link->edit($label, 'https://injust.test', null, false, true);
                self::fail('A label over 80 characters was accepted.');
            } catch (InvalidLinkLabel $refused) {
                self::assertSame(['max' => 80], $refused->parameters());
            }
        }
    }
}
