<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Shared\ImageFocusQueries;
use App\Domain\Shared\ImageFocus;
use App\Domain\Shared\ImageFocusRepository;
use App\Presentation\Web\Site\ImageFocusClasses;
use PHPUnit\Framework\TestCase;

final class ImageFocusClassesTest extends TestCase
{
    public function testAFocusedImageGetsItsClasses(): void
    {
        $classes = new ImageFocusClasses(new ImageFocusQueries(self::repository([self::focus('01JA', 35, 60), self::focus('01JB', 0, 100)])));

        self::assertSame('fx-35 fy-60', $classes->of('01JA'));
        self::assertSame('fx-0 fy-100', $classes->of('01JB'));
        self::assertSame('', $classes->of('01JC'));
        self::assertSame('', $classes->of(null));
    }

    public function testTheFocusesAreLoadedOncePerRequest(): void
    {
        $focuses = $this->createMock(ImageFocusRepository::class);
        $focuses->expects($this->exactly(2))->method('all')->willReturnOnConsecutiveCalls([self::focus('01JA', 35, 60)], []);
        $classes = new ImageFocusClasses(new ImageFocusQueries($focuses));

        self::assertSame('', $classes->of(null));
        self::assertSame('fx-35 fy-60', $classes->of('01JA'));
        self::assertSame('fx-35 fy-60', $classes->of('01JA'));
        $classes->reset();
        self::assertSame('', $classes->of('01JA'));
    }

    /** @param list<ImageFocus> $focuses */
    private static function repository(array $focuses): ImageFocusRepository
    {
        $repository = self::createStub(ImageFocusRepository::class);
        $repository->method('all')->willReturn($focuses);

        return $repository;
    }

    private static function focus(string $image, int $x, int $y): ImageFocus
    {
        $focus = ImageFocus::centered($image);
        $focus->moveTo($x, $y);

        return $focus;
    }
}
