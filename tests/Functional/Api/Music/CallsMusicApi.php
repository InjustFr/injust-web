<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Music;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait CallsMusicApi
{
    use SignsInClient;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        new Filesystem()->remove(self::$kernel?->getShareDir() ?? []);
        parent::tearDown();
    }

    /**
     * @param array<mixed> $body
     *
     * @return array<mixed>
     */
    private static function send(KernelBrowser $client, string $method, string $url, array $body = []): array
    {
        $client->jsonRequest($method, $url, $body);
        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        return '' === $content ? [] : Json::decode($content);
    }

    /** @param array<mixed> $body */
    private static function refused(KernelBrowser $client, string $method, string $url, array $body = [], int $status = 422): string
    {
        $client->jsonRequest($method, $url, $body);
        self::assertResponseStatusCodeSame($status);
        $problem = Json::decode((string) $client->getResponse()->getContent());

        return \is_string($problem['detail'] ?? null) ? $problem['detail'] : '';
    }

    /** @return array<mixed> */
    private static function upload(KernelBrowser $client, string $url, string $fixture = 'large-fern.png'): array
    {
        $copy = tempnam(sys_get_temp_dir(), 'image');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 3).'/Fixtures/'.$fixture, $copy);
        $client->request('POST', $url, files: ['file' => new UploadedFile($copy, 'image.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);

        return Json::decode((string) $client->getResponse()->getContent());
    }

    private static function imageExists(KernelBrowser $client, string $image): bool
    {
        $client->request('GET', '/media/images/'.$image.'.webp');

        return $client->getResponse()->isSuccessful();
    }
}
