<?php

declare(strict_types=1);

namespace App\Presentation\Web\Webhooks;

final readonly class StripeEvent
{
    /** @param array<mixed> $object */
    private function __construct(
        public string $type,
        private array $object,
    ) {
    }

    public static function parse(string $payload): ?self
    {
        $event = json_decode($payload, true);
        if (!\is_array($event)) {
            return null;
        }
        $type = $event['type'] ?? null;
        $data = $event['data'] ?? null;
        $object = \is_array($data) ? $data['object'] ?? null : null;

        return \is_string($type) && \is_array($object) ? new self($type, $object) : null;
    }

    public function text(string $field): string
    {
        $value = $this->object[$field] ?? null;

        return \is_string($value) ? $value : '';
    }

    public function flag(string $field): bool
    {
        return true === ($this->object[$field] ?? null);
    }
}
