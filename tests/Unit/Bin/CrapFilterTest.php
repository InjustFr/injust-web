<?php

declare(strict_types=1);

namespace App\Tests\Unit\Bin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tooling\CrapFilter;

final class CrapFilterTest extends TestCase
{
    #[DataProvider('paths')]
    public function testASourcePathMapsToItsClass(string $path, ?string $class): void
    {
        self::assertSame($class, CrapFilter::classOf($path));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function paths(): iterable
    {
        yield 'nested class' => ['src/Domain/Settings/Settings.php', 'App\Domain\Settings\Settings'];
        yield 'root class' => ['src/Kernel.php', 'App\Kernel'];
        yield 'test file' => ['tests/Unit/FooTest.php', null];
        yield 'twig' => ['src/Foo/bar.twig', null];
        yield 'outside src' => ['vendor/src/Foo.php', null];
    }

    public function testOnlyChangedClassesAboveTheThresholdAreReported(): void
    {
        $xml = $this->report([
            ['App\Domain\A', 'big', 12.5],
            ['App\Domain\A', 'small', 3],
            ['App\Domain\B', 'big', 30],
            ['App\Domain\C', 'exact', 10],
        ]);

        $violations = CrapFilter::violations($xml, 10, ['src/Domain/A.php', 'src/Domain/C.php', 'tests/Foo.php']);

        self::assertSame([['method' => 'App\Domain\A::big', 'crap' => 12.5]], $violations);
    }

    public function testNothingChangedMeansNothingReported(): void
    {
        self::assertSame([], CrapFilter::violations($this->report([['App\Domain\A', 'big', 99]]), 10, []));
    }

    public function testAnUnreadableReportIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CrapFilter::violations('not xml', 10, ['src/Kernel.php']);
    }

    /**
     * @param list<array{string, string, int|float}> $methods
     */
    private function report(array $methods): string
    {
        $rows = '';
        foreach ($methods as [$class, $method, $crap]) {
            $rows .= "<method><package>x</package><className>$class</className><methodName>$method</methodName><crap>$crap</crap></method>";
        }

        return "<?xml version=\"1.0\"?><crap_result><project name=\"\"><methods>$rows</methods></project></crap_result>";
    }
}
