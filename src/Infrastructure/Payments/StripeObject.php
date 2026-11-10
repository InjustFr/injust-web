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

    /** @return list<self> */
    public function list(string $path): array
    {
        $items = $this->at($path);
        if (!\is_array($items)) {
            throw self::unexpected($path);
        }

        return array_values(array_map(static fn (mixed $item): self => \is_array($item) ? new self($item) : throw self::unexpected($path), $items));
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
