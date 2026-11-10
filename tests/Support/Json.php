<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;

final class Json
{
    /**
     * @return array<mixed>
     */
    public static function decode(string $json): array
    {
        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertIsArray($data);

        return $data;
    }

    public static function at(mixed $data, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            Assert::assertIsArray($data);
            Assert::assertArrayHasKey($key, $data);
            $data = $data[$key];
        }

        return $data;
    }

    public static function string(mixed $data, string|int ...$path): string
    {
        $value = self::at($data, ...$path);
        Assert::assertIsString($value);

        return $value;
    }

    public static function int(mixed $data, string|int ...$path): int
    {
        $value = self::at($data, ...$path);
        Assert::assertIsInt($value);

        return $value;
    }

    /**
     * @return array<mixed>
     */
    public static function array(mixed $data, string|int ...$path): array
    {
        $value = self::at($data, ...$path);
        Assert::assertIsArray($value);

        return $value;
    }
}
