<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Shared\AudioStorage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final class AudioMediaTest extends WebTestCase
{
    private const string PREVIEW = "ID3\x04\x00\x00\x00\x00\x00\x00preview-bytes";

    protected function tearDown(): void
    {
        new Filesystem()->remove(self::$kernel?->getShareDir() ?? []);
        parent::tearDown();
    }

    public function testAPreviewIsServedForAYearWithoutASession(): void
    {
        $client = self::createClient();
        $id = self::store();

        $client->request('GET', '/media/audio/'.$id.'.mp3');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'audio/mpeg');
        self::assertResponseHeaderSame('Accept-Ranges', 'bytes');
        self::assertStringContainsString('immutable', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('max-age=31536000', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(self::PREVIEW, $client->getInternalResponse()->getContent());
        self::assertSame([], $client->getResponse()->headers->getCookies());
    }

    public function testARangeRequestGetsPartialContent(): void
    {
        $client = self::createClient();
        $id = self::store();

        $client->request('GET', '/media/audio/'.$id.'.mp3', server: ['HTTP_RANGE' => 'bytes=3-9']);

        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Range', 'bytes 3-9/'.\strlen(self::PREVIEW));
        self::assertSame(substr(self::PREVIEW, 3, 7), $client->getInternalResponse()->getContent());
    }

    public function testAMissingPreviewIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/media/audio/'.new Ulid()->toBase32().'.mp3');

        self::assertResponseStatusCodeSame(404);
    }

    private static function store(): string
    {
        $upload = tempnam(sys_get_temp_dir(), 'preview');
        self::assertIsString($upload);
        file_put_contents($upload, self::PREVIEW);

        return self::getContainer()->get(AudioStorage::class)->store($upload);
    }
}
