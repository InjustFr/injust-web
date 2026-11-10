<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\Exception\InvalidFocus;
use App\Domain\Shared\ImageFocus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageFocusTest extends TestCase
{
    public function testAFocusStartsCentered(): void
    {
        $focus = ImageFocus::centered('01JIMAGE');

        self::assertSame(['01JIMAGE', 50, 50, true], [$focus->image(), $focus->x(), $focus->y(), $focus->isCentered()]);
    }

    public function testAFocusMovesToEveryEdge(): void
    {
        $focus = ImageFocus::centered('01JIMAGE');

        $focus->moveTo(0, 100);
        self::assertSame([0, 100, false], [$focus->x(), $focus->y(), $focus->isCentered()]);
        $focus->moveTo(100, 0);
        self::assertSame([100, 0], [$focus->x(), $focus->y()]);
    }

    public function testAFocusOffCentreOnOneAxisIsNotCentered(): void
    {
        $focus = ImageFocus::centered('01JIMAGE');

        $focus->moveTo(50, 55);
        self::assertFalse($focus->isCentered());
        $focus->moveTo(45, 50);
        self::assertFalse($focus->isCentered());
        $focus->moveTo(50, 50);
        self::assertTrue($focus->isCentered());
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidFocuses(): iterable
    {
        yield 'x below zero' => [-5, 50];
        yield 'x above a hundred' => [105, 50];
        yield 'y below zero' => [50, -5];
        yield 'y above a hundred' => [50, 105];
        yield 'x off step' => [33, 50];
        yield 'y off step' => [50, 1];
    }

    #[DataProvider('invalidFocuses')]
    public function testAFocusOutsideTheGridIsRefused(int $x, int $y): void
    {
        $focus = ImageFocus::centered('01JIMAGE');

        try {
            $focus->moveTo($x, $y);
            self::fail('The focus was accepted.');
        } catch (InvalidFocus $refused) {
            self::assertSame('shared.invalid_focus', $refused->getMessage());
            self::assertSame(['step' => 5], $refused->parameters());
        }
        self::assertSame([50, 50], [$focus->x(), $focus->y()]);
    }
}
