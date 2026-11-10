<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Presentation\Web\Site\BeatFilter;
use PHPUnit\Framework\TestCase;

final class BeatFilterTest extends TestCase
{
    public function testTheQueryGivesTagsKeyAndBpmBounds(): void
    {
        $filter = BeatFilter::from(['tag' => ['riot', 3, 'dark'], 'key' => 'a-minor', 'bpm_min' => '90', 'bpm_max' => '140']);

        self::assertSame(['riot', 'dark'], $filter->tags);
        self::assertSame('a-minor', $filter->key);
        self::assertSame(90, $filter->min);
        self::assertSame(140, $filter->max);
    }

    public function testMalformedValuesAreIgnored(): void
    {
        $filter = BeatFilter::from(['tag' => 'riot', 'key' => ['a-minor'], 'bpm_min' => '-5', 'bpm_max' => ['140']]);

        self::assertSame([], $filter->tags);
        self::assertSame('', $filter->key);
        self::assertNull($filter->min);
        self::assertNull($filter->max);
        self::assertNull(BeatFilter::from(['bpm_min' => '9x'])->min);
    }

    public function testAnEmptyFilterKeepsEveryBeat(): void
    {
        self::assertTrue(BeatFilter::from([])->keeps(self::row(['riot'], 'a-minor', 120)));
    }

    public function testEveryTagAsked(): void
    {
        $filter = BeatFilter::from(['tag' => ['riot', 'dark']]);

        self::assertTrue($filter->keeps(self::row(['dark', 'fast', 'riot'], 'a-minor', 120)));
        self::assertFalse($filter->keeps(self::row(['riot'], 'a-minor', 120)));
    }

    public function testTheKeyMustMatch(): void
    {
        $filter = BeatFilter::from(['key' => 'a-minor']);

        self::assertTrue($filter->keeps(self::row([], 'a-minor', 120)));
        self::assertFalse($filter->keeps(self::row([], 'c-major', 120)));
    }

    public function testTheBpmBoundsAreInclusive(): void
    {
        $filter = BeatFilter::from(['bpm_min' => '90', 'bpm_max' => '140']);

        self::assertTrue($filter->keeps(self::row([], 'a-minor', 90)));
        self::assertTrue($filter->keeps(self::row([], 'a-minor', 140)));
        self::assertFalse($filter->keeps(self::row([], 'a-minor', 89)));
        self::assertFalse($filter->keeps(self::row([], 'a-minor', 141)));
    }

    /**
     * @param list<string> $tags
     *
     * @return array{tags: list<string>, key: string, bpm: int}
     */
    private static function row(array $tags, string $key, int $bpm): array
    {
        return ['tags' => $tags, 'key' => $key, 'bpm' => $bpm];
    }
}
