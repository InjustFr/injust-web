<?php

declare(strict_types=1);

namespace App\Presentation\Web\Webhooks;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class StripeSignature
{
    private const int TOLERANCE = 300;

    public function __construct(
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private string $secret,
        private ClockInterface $clock,
    ) {
    }

    public function sign(string $payload): string
    {
        $timestamp = (string) $this->clock->now()->getTimestamp();

        return 't='.$timestamp.',v1='.$this->hash($timestamp, $payload);
    }

    public function verify(string $payload, ?string $header): bool
    {
        $timestamp = '';
        $signatures = [];
        foreach (explode(',', $header ?? '') as $part) {
            [$key, $value] = explode('=', trim($part), 2) + ['', ''];
            if ('t' === $key) {
                $timestamp = $value;
            } elseif ('v1' === $key) {
                $signatures[] = $value;
            }
        }

        return '' !== $this->secret && $this->fresh($timestamp) && $this->matches($this->hash($timestamp, $payload), $signatures);
    }

    private function fresh(string $timestamp): bool
    {
        return ctype_digit($timestamp) && abs($this->clock->now()->getTimestamp() - (int) $timestamp) <= self::TOLERANCE;
    }

    /** @param list<string> $signatures */
    private function matches(string $expected, array $signatures): bool
    {
        return array_any($signatures, static fn (string $signature): bool => hash_equals($expected, $signature));
    }

    private function hash(string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret);
    }
}
