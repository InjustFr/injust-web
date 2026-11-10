<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Shared\ImageStorage;
use App\Infrastructure\Storage\GdImageResizer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class MediaTest extends WebTestCase
{
    public function testAStoredImageIsServedForAYear(): void
    {
        $client = self::createClient();
        $file = self::getContainer()->get(ImageStorage::class)->store(new Ulid(), new GdImageResizer()->resize(__DIR__.'/../../Fixtures/large-fern.png'));

        $client->request('GET', '/media/images/'.$file);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertStringContainsString('immutable', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testAMissingImageIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/media/images/'.new Ulid()->toBase32().'.webp');

        self::assertResponseStatusCodeSame(404);
    }
}
