<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\SocialLink;
use App\Domain\Music\SocialNetwork;
use App\Domain\Shared\Exception\InvalidUrl;
use PHPUnit\Framework\TestCase;

final class SocialLinkTest extends TestCase
{
    public function testALinkKeepsItsNetworkUrlLabelAndPosition(): void
    {
        $link = SocialLink::add(SocialNetwork::Linktree, ' https://linktr.ee/injust ', ' All links ', 3);

        self::assertSame([SocialNetwork::Linktree, 'https://linktr.ee/injust', 'All links', 3], [$link->network(), $link->url(), $link->label(), $link->position()]);
    }

    public function testABlankLabelMeansNone(): void
    {
        $link = SocialLink::add(SocialNetwork::Instagram, 'https://instagram.com/injust', 'x', 0);

        $link->edit(SocialNetwork::Tiktok, 'https://tiktok.com/@injust', '  ');

        self::assertSame([SocialNetwork::Tiktok, 'https://tiktok.com/@injust', null], [$link->network(), $link->url(), $link->label()]);
    }

    public function testTheUrlMustBeHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        SocialLink::add(SocialNetwork::Other, 'http://injust.test', null, 0);
    }

    public function testAnEditedUrlMustBeHttpsToo(): void
    {
        $link = SocialLink::add(SocialNetwork::Other, 'https://injust.test', null, 0);

        $this->expectException(InvalidUrl::class);

        $link->edit(SocialNetwork::Other, '', null);
    }
}
