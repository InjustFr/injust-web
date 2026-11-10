<?php

declare(strict_types=1);

namespace App\Tests\Functional\Persistence\Links;

use App\Application\Transaction;
use App\Domain\Links\BioLink;
use App\Domain\Links\BioLinkRepository;
use App\Domain\Shared\LocalizedText;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class BioLinkRepositoryTest extends KernelTestCase
{
    public function testAClickIsOneUpdateThatAStaleCopyDoesNotOverwrite(): void
    {
        $links = self::getContainer()->get(BioLinkRepository::class);
        $link = BioLink::add(new LocalizedText('Merch', 'Merch'), 'https://injust.test/merch', null, false, true, 0);
        $links->add($link);
        self::getContainer()->get(Transaction::class)->commit();

        self::assertSame('https://injust.test/merch', $links->countClick($link->id()));
        self::assertSame('https://injust.test/merch', $links->countClick($link->id()));
        $link->edit(new LocalizedText('Boutique', 'Shop'), 'https://injust.test/shop', null, false, true);
        self::getContainer()->get(Transaction::class)->commit();

        self::assertSame(2, self::storedClicks($link->id()));
        self::assertSame(0, $link->clicks());
    }

    public function testOnlyAVisibleStoredLinkIsCounted(): void
    {
        $links = self::getContainer()->get(BioLinkRepository::class);
        $hidden = BioLink::add(new LocalizedText('Old', 'Old'), 'https://injust.test/old', null, false, false, 0);
        $links->add($hidden);
        self::getContainer()->get(Transaction::class)->commit();

        self::assertNull($links->countClick($hidden->id()));
        self::assertNull($links->countClick(new Ulid()));
        self::assertSame(0, self::storedClicks($hidden->id()));
    }

    public function testLinksAreListedByPosition(): void
    {
        $links = self::getContainer()->get(BioLinkRepository::class);
        $second = BioLink::add(new LocalizedText('B', 'B'), 'https://injust.test/b', null, false, true, 1);
        $first = BioLink::add(new LocalizedText('A', 'A'), 'https://injust.test/a', null, false, true, 0);
        $links->add($second);
        $links->add($first);
        self::getContainer()->get(Transaction::class)->commit();
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame([$first->id()->toBase32(), $second->id()->toBase32()], array_map(static fn (BioLink $link): string => $link->id()->toBase32(), $links->all()));
    }

    private static function storedClicks(Ulid $id): int
    {
        $clicks = self::getContainer()->get(Connection::class)->fetchOne('SELECT clicks FROM bio_link WHERE id = :id', ['id' => $id->toRfc4122()]);
        self::assertIsInt($clicks);

        return $clicks;
    }
}
