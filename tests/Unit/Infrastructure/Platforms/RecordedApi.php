<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Platforms;

use App\Application\Music\ReleaseLookup;
use App\Domain\Music\ReleaseType;
use App\Infrastructure\Platforms\PlatformApi;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

trait RecordedApi
{
    /** @var list<string> */
    private array $requests = [];

    /** @var list<?string> */
    private array $authorizations = [];

    /** @var list<?string> */
    private array $accepts = [];

    /** @var list<?string> */
    private array $apiKeys = [];

    /** @param array<string, MockResponse|callable(): MockResponse> $answers */
    private function api(array $answers, ?ArrayAdapter $tokens = null): PlatformApi
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($answers): MockResponse {
            $this->requests[] = $method.' '.$url;
            $this->authorizations[] = self::header($options, 'authorization');
            $this->accepts[] = self::header($options, 'accept');
            $this->apiKeys[] = self::header($options, 'x-goog-api-key');
            foreach ($answers as $prefix => $answer) {
                if (str_starts_with($method.' '.$url, $prefix)) {
                    return $answer instanceof MockResponse ? $answer : $answer();
                }
            }
            self::fail('Unexpected request '.$method.' '.$url);
        });

        return new PlatformApi($client, $tokens ?? new ArrayAdapter());
    }

    /** @param array<mixed> $options */
    private static function header(array $options, string $name): ?string
    {
        $headers = $options['normalized_headers'] ?? null;
        $values = \is_array($headers) ? $headers[$name] ?? null : null;

        return \is_array($values) && \is_string($values[0] ?? null) ? $values[0] : null;
    }

    private static function release(): ReleaseLookup
    {
        return new ReleaseLookup('701518909440', 'White Men are the Issue', ReleaseType::Single);
    }

    /** @param array<mixed> $body */
    private static function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body, \JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }
}
