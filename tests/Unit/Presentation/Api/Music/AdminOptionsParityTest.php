<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api\Music;

use App\Domain\Music\Platform;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\SocialNetwork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminOptionsParityTest extends TestCase
{
    /** @param class-string<\BackedEnum> $enum */
    #[DataProvider('options')]
    public function testTheAdminOptionsListEveryEnumCaseInOrder(string $constant, string $enum): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 5).'/assets/vue/admin/musicForm.js');
        self::assertSame(1, preg_match('/^export const '.$constant.' = \[(.*)\];$/m', $source, $match));
        preg_match_all("/'([^']+)'/", $match[1], $values);

        self::assertSame(array_map(static fn (\BackedEnum $case): int|string => $case->value, $enum::cases()), $values[1]);
    }

    /** @return iterable<array{string, class-string<\BackedEnum>}> */
    public static function options(): iterable
    {
        yield ['RELEASE_TYPES', ReleaseType::class];
        yield ['PLATFORMS', Platform::class];
        yield ['NETWORKS', SocialNetwork::class];
    }
}
