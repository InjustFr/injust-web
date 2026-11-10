<?php

declare(strict_types=1);

namespace App\Infrastructure\Platforms;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PlatformApi
{
    private const float TIMEOUT = 5.0;
    private const int TOKEN_MARGIN = 60;

    private HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        #[Autowire(service: 'cache.platform_tokens')]
        private CacheInterface $tokens,
    ) {
        $this->client = $client->withOptions(['headers' => ['User-Agent' => 'injust-web'], 'timeout' => self::TIMEOUT, 'max_duration' => self::TIMEOUT]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    public function json(string $method, string $url, array $options = []): array
    {
        return $this->client->request($method, $url, $options)->toArray();
    }

    public function token(string $platform, string $url, string $clientId, string $secret): string
    {
        return $this->tokens->get($platform.'.'.hash('xxh128', $clientId), function (ItemInterface $item) use ($url, $clientId, $secret): string {
            $answer = $this->json('POST', $url, ['auth_basic' => [$clientId, $secret], 'body' => ['grant_type' => 'client_credentials']]);
            $token = $answer['access_token'] ?? null;
            $lifetime = $answer['expires_in'] ?? null;
            if (!\is_string($token) || !\is_int($lifetime)) {
                throw new \UnexpectedValueException('The token answer has no usable access_token or expires_in.');
            }
            $item->expiresAfter(max(1, $lifetime - self::TOKEN_MARGIN));

            return $token;
        });
    }

    /** @param array<mixed> $data */
    public static function at(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $key) {
            $value = \is_array($value) ? $value[$key] ?? null : null;
        }

        return $value;
    }
}
