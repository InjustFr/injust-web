<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exception\OrderMismatch;

final class Positions
{
    /**
     * @param list<string>     $ids
     * @param list<Positioned> $items
     */
    public static function apply(array $ids, array $items): void
    {
        $byId = [];
        foreach ($items as $item) {
            $byId[$item->id()->toBase32()] = $item;
        }
        $given = $ids;
        $known = array_keys($byId);
        sort($given);
        sort($known);
        if ($given !== $known) {
            throw new OrderMismatch();
        }
        foreach ($ids as $position => $id) {
            $byId[$id]->moveTo($position);
        }
    }
}
