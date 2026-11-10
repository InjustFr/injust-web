<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Beats;

use App\Domain\Beats\Beat;
use App\Tests\Functional\Api\Music\CallsMusicApi;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class BeatFilesApiTest extends WebTestCase
{
    use CallsMusicApi;
    use CallsBeatsApi;

    private const string WAV = "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00";
    private const string ZIP = "PK\x03\x04\x14\x00\x00\x00stems";

    public function testAPreviewIsUploadedServedReplacedAndRemoved(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);

        $first = Json::array(self::uploadAudio($client, '/api/admin/beats/'.$id.'/preview', self::MP3, 'Riot Gear (tag).mp3'), 'preview');
        $second = Json::string(self::uploadAudio($client, '/api/admin/beats/'.$id.'/preview', "\xFF\xFB\x90\x00frame"), 'preview', 'id');

        self::assertSame(['originalName' => 'Riot Gear (tag).mp3', 'size' => \strlen(self::MP3)], array_diff_key($first, ['id' => true]));
        self::assertSame([false, true], [self::audioIsServed($client, Json::string($first, 'id')), self::audioIsServed($client, $second)]);
        self::assertSame($second, Json::string(self::send($client, 'GET', '/api/admin/beats'), 'beats', 0, 'preview', 'id'));
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/beats/'.$id.'/preview'), 'preview'));
        self::assertFalse(self::audioIsServed($client, $second));
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/beats/'.$id.'/preview'), 'preview'));
        self::assertSame(0, self::storedFiles());
    }

    public function testAVisibleBeatIsListedOnceItHasAPreview(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);
        self::send($client, 'PUT', '/api/admin/beats/'.$id, [...self::BEAT, 'visible' => true]);

        self::assertTrue(Json::at(self::uploadAudio($client, '/api/admin/beats/'.$id.'/preview', self::MP3), 'listed'));
    }

    public function testDeliverablesAreUploadedWithTheirMetadataButNoPath(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);

        self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/mp3', self::MP3, 'Riot.mp3');
        self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/wav', self::WAV, 'Riot.wav');
        $beat = self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/stems', self::ZIP, 'Riot stems.zip');

        self::assertSame(['mp3' => ['originalName' => 'Riot.mp3', 'size' => \strlen(self::MP3)], 'wav' => ['originalName' => 'Riot.wav', 'size' => \strlen(self::WAV)], 'stems' => ['originalName' => 'Riot stems.zip', 'size' => \strlen(self::ZIP)]], Json::array($beat, 'files'));
        self::assertSame(Json::array($beat, 'files'), Json::array(self::send($client, 'GET', '/api/admin/beats'), 'beats', 0, 'files'));
        $stored = self::storedIds($id);
        self::assertCount(3, $stored);
        foreach ([json_encode($beat, \JSON_THROW_ON_ERROR), (string) $client->getResponse()->getContent()] as $json) {
            self::assertStringNotContainsString((string) self::$kernel?->getShareDir(), $json);
            self::assertStringNotContainsString('private', $json);
            foreach ($stored as $file) {
                self::assertStringNotContainsString($file, $json);
            }
        }
    }

    public function testPrivateFilesAreNeverReachableOverHttp(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);
        self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/mp3', self::MP3);
        $file = self::storedIds($id)[0];
        $share = 'var/share/'.basename((string) self::$kernel?->getShareDir());

        foreach (['/media/audio/'.$file.'.mp3', '/media/images/'.$file.'.webp', '/media/private/'.$file, '/'.$share.'/private/'.$file, '/var/share/private/'.$file, '/private/'.$file, '/media/audio/'.$file] as $url) {
            $client->request('GET', $url);
            self::assertSame(404, $client->getResponse()->getStatusCode(), $url);
        }
        self::assertFileExists(self::$kernel?->getShareDir().'/private/'.$file);
    }

    public function testAReplacedOrRemovedFileLeavesTheDisk(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);
        self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/wav', self::WAV);
        $first = self::storedIds($id)[0];

        self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/wav', self::WAV.'v2', 'v2.wav');
        $second = self::storedIds($id)[0];

        self::assertNotSame($first, $second);
        self::assertSame([false, true], [is_file(self::privatePath($first)), is_file(self::privatePath($second))]);
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/beats/'.$id.'/files/wav'), 'files', 'wav'));
        self::assertFileDoesNotExist(self::privatePath($second));
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/beats/'.$id.'/files/wav'), 'files', 'wav'));
    }

    public function testADeletedBeatTakesItsFilesWithIt(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);
        self::uploadAudio($client, '/api/admin/beats/'.$id.'/preview', self::MP3);
        self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/mp3', self::MP3);
        self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/stems', self::ZIP);
        self::assertSame(3, self::storedFiles());

        self::send($client, 'DELETE', '/api/admin/beats/'.$id);

        self::assertSame(0, self::storedFiles());
    }

    public function testAFileOfTheWrongKindIsRefusedAndNotKept(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);

        $problem = self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/wav', self::MP3);
        self::assertSame([422, 'This file is not a WAV file: export the beat again and upload that file.'], [Json::int($problem, 'status'), Json::string($problem, 'detail')]);
        self::assertSame('This file is not an MP3 file: export the beat again and upload that file.', Json::string(self::uploadAudio($client, '/api/admin/beats/'.$id.'/preview', self::WAV), 'detail'));
        self::assertSame('This file is not a ZIP archive: put the stems in a .zip file and upload that.', Json::string(self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/stems', self::MP3), 'detail'));
        self::assertSame(422, Json::int(self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/mp3', self::WAV), 'status'));
        self::assertSame(422, Json::int(self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/mp3', ''), 'status'));
        self::assertSame('The upload did not complete: check the file size and your connection, then try again.', Json::string(self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/mp3', null), 'detail'));
        self::assertSame(422, Json::int(self::uploadAudio($client, '/api/admin/beats/'.$id.'/preview', null), 'status'));

        self::assertSame(0, self::storedFiles());
        self::assertSame(['mp3' => null, 'wav' => null, 'stems' => null], Json::array(self::send($client, 'GET', '/api/admin/beats'), 'beats', 0, 'files'));
    }

    public function testAnUnknownDeliverableIsNotARoute(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);

        self::assertSame(404, Json::int(self::uploadAudio($client, '/api/admin/beats/'.$id.'/files/flac', self::MP3), 'status'));
    }

    /** @return list<string> */
    private static function storedIds(string $beat): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $found = $entityManager->find(Beat::class, Ulid::fromString($beat));
        self::assertInstanceOf(Beat::class, $found);

        return array_map(static fn ($file): string => $file->id, $found->files());
    }

    private static function privatePath(string $id): string
    {
        return self::$kernel?->getShareDir().'/private/'.$id;
    }
}
