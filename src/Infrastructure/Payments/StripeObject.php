<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments;

final readonly class StripeObject
{
    /** @param array<mixed> $data */
    public function __construct(private array $data)
    {
    }

    public function string(string $path): string
    {
        $value = $this->at($path);

        return \is_string($value) ? $value : throw self::unexpected($path);
    }

    public function optional(string $path): ?string
    {
        $value = $this->at($path);

        return \is_string($value) ? $value : null;
    }

    public function int(string $path): int
    {
        $value = $this->at($path);

        return \is_int($value) ? $value : throw self::unexpected($path);
    }

    private function at(string $path): mixed
    {
        $value = $this->data;
        foreach (explode('.', $path) as $key) {
            $value = \is_array($value) ? $value[$key] ?? null : null;
        }

        return $value;
    }

    private static function unexpected(string $path): \UnexpectedValueException
    {
        return new \UnexpectedValueException(\sprintf('Stripe answered without a usable "%s".', $path));
    }
}
