<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FakeAccounts implements HttpClientInterface
{
    use DecoratorTrait;

    private const string URL = 'https://accounts.test/';

    /** @param array<string, mixed> $claims */
    public static function code(array $claims): string
    {
        return rtrim(strtr(base64_encode(json_encode($claims, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!str_starts_with($url, self::URL)) {
            return $this->client->request($method, $url, $options);
        }

        return new MockHttpClient(self::answer(...))->request($method, $url, $options);
    }

    /** @param array<string, mixed> $options */
    private static function answer(string $method, string $url, array $options): JsonMockResponse
    {
        return match ($url) {
            self::URL.'token' => self::token($options),
            self::URL.'userinfo' => self::userinfo($options),
            default => new JsonMockResponse(['error' => 'not_found'], ['http_code' => 404]),
        };
    }

    /** @param array<string, mixed> $options */
    private static function token(array $options): JsonMockResponse
    {
        $body = $options['body'] ?? '';
        parse_str(\is_string($body) ? $body : '', $form);
        $code = \is_string($form['code'] ?? null) ? $form['code'] : '';
        if ('test' !== ($form['client_secret'] ?? null) || '' === ($form['code_verifier'] ?? '') || null === self::claims($code)) {
            return new JsonMockResponse(['error' => 'invalid_grant'], ['http_code' => 400]);
        }

        return new JsonMockResponse(['access_token' => $code, 'token_type' => 'Bearer']);
    }

    /** @param array<string, mixed> $options */
    private static function userinfo(array $options): JsonMockResponse
    {
        $headers = \is_array($options['normalized_headers'] ?? null) ? $options['normalized_headers'] : [];
        $authorization = \is_array($headers['authorization'] ?? null) ? $headers['authorization'][0] ?? '' : '';
        $claims = self::claims(\is_string($authorization) ? substr($authorization, \strlen('Authorization: Bearer ')) : '');

        return null === $claims ? new JsonMockResponse(['error' => 'invalid_token'], ['http_code' => 401]) : new JsonMockResponse($claims);
    }

    /** @return ?array<mixed> */
    private static function claims(string $token): ?array
    {
        $claims = json_decode((string) base64_decode(strtr($token, '-_', '+/'), true), true);

        return \is_array($claims) ? $claims : null;
    }
}
