<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Exception\OrderMismatch;
use App\Domain\Music\Positions;
use App\Domain\Music\SocialLink;
use App\Domain\Music\SocialNetwork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PositionsTest extends TestCase
{
    public function testEachItemTakesItsIndexInTheGivenOrder(): void
    {
        [$a, $b, $c] = self::links();

        Positions::apply([self::id($c), self::id($a), self::id($b)], [$c, $b, $a]);

        self::assertSame([1, 2, 0], [$a->position(), $b->position(), $c->position()]);
    }

    /** @return iterable<array{list<int>}> */
    public static function mismatches(): iterable
    {
        yield 'one missing' => [[0, 1]];
        yield 'one twice' => [[0, 1, 1]];
        yield 'one twice, one missing' => [[0, 0, 2]];
        yield 'one unknown' => [[0, 1, 2, 3]];
        yield 'none' => [[]];
    }

    /** @param list<int> $picked */
    #[DataProvider('mismatches')]
    public function testTheIdsMustBeExactlyTheExistingOnes(array $picked): void
    {
        $links = self::links();
        $ids = array_map(static fn (int $index): string => isset($links[$index]) ? self::id($links[$index]) : '01ARZ3NDEKTSV4RRFFQ69G5FAV', $picked);

        try {
            Positions::apply($ids, $links);
            self::fail('The order was accepted.');
        } catch (OrderMismatch $refused) {
            self::assertSame('music.order_mismatch', $refused->getMessage());
            self::assertSame([0, 1, 2], array_map(static fn (SocialLink $link): int => $link->position(), $links));
        }
    }

    /** @return list<SocialLink> */
    private static function links(): array
    {
        return array_map(static fn (int $position): SocialLink => SocialLink::add(SocialNetwork::Instagram, 'https://instagram.com/injust', null, $position), [0, 1, 2]);
    }

    private static function id(SocialLink $link): string
    {
        return $link->id()->toBase32();
    }
}
